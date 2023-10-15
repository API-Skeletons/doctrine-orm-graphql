<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\GraphQL\Resolve;

use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Criteria\Filters as FiltersDef;
use ApiSkeletons\Doctrine\GraphQL\Event\FilterCriteria;
use ApiSkeletons\Doctrine\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\GraphQL\Type\TypeManager;
use ArrayObject;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;

use function base64_decode;
use function base64_encode;
use function count;

class ResolveCollectionFactory
{
    public function __construct(
        protected EntityManager $entityManager,
        protected Config $config,
        protected FieldResolver $fieldResolver,
        protected TypeManager $typeManager,
        protected EventDispatcher $eventDispatcher,
        protected ArrayObject $metadata,
    ) {
    }

    public function parseValue(ClassMetadata $metadata, string $field, mixed $value): mixed
    {
        /** @psalm-suppress UndefinedDocblockClass */
        $fieldMapping = $metadata->getFieldMapping($field);
        $graphQLType  = $this->typeManager->get($fieldMapping['type']);

        return $graphQLType->parseValue($graphQLType->serialize($value));
    }

    /** @param mixed[] $value */
    public function parseArrayValue(ClassMetadata $metadata, string $field, array $value): mixed
    {
        foreach ($value as $key => $val) {
            $value[$key] = $this->parseValue($metadata, $field, $val);
        }

        return $value;
    }

    public function get(Entity $entity): Closure
    {
        return function ($source, array $args, $context, ResolveInfo $info) {
            $fieldResolver = $this->fieldResolver;
            $collection    = $fieldResolver($source, $args, $context, $info);

            $entityClassName = ClassUtils::getRealClass($source::class);

            $targetClassName = (string) $this->entityManager->getMetadataFactory()
                ->getMetadataFor($entityClassName)
                ->getAssociationTargetClass($info->fieldName);

            $collectionMetadata = $this->entityManager->getMetadataFactory()
                ->getMetadataFor($targetClassName);

            return $this->buildPagination(
                $entityClassName,
                $targetClassName,
                $args['pagination'] ?? [],
                $collection,
                $this->buildCriteria($args['filter'] ?? [], $collectionMetadata),
                $this->metadata[$entityClassName]['fields'][$info->fieldName]['filterCriteriaEventName'],
                $source,
                $args,
                $context,
                $info,
            );
        };
    }

    /** @param mixed[] $filter */
    private function buildCriteria(array $filter, ClassMetadata $collectionMetadata): Criteria
    {
        $orderBy  = [];
        $criteria = Criteria::create();

        foreach ($filter as $field => $filters) {
            foreach ($filters as $filter => $value) {
                switch ($filter) {
                    case FiltersDef::IN:
                    case FiltersDef::NOTIN:
                        $value = $this->parseArrayValue($collectionMetadata, $field, $value);
                        $criteria->andWhere($criteria->expr()->$filter($field, $value));
                        break;
                    case FiltersDef::ISNULL:
                        $criteria->andWhere($criteria->expr()->$filter($field));
                        break;
                    case FiltersDef::BETWEEN:
                        $value = $this->parseArrayValue($collectionMetadata, $field, $value);

                        $criteria->andWhere($criteria->expr()->gte($field, $value['from']));
                        $criteria->andWhere($criteria->expr()->lte($field, $value['to']));
                        break;
                    case FiltersDef::SORT:
                        $orderBy[$field] = $value;
                        break;
                    default:
                        $value = $this->parseValue($collectionMetadata, $field, $value);
                        $criteria->andWhere($criteria->expr()->$filter($field, $value));
                        break;
                }
            }
        }

        if (! empty($orderBy)) {
            $criteria->orderBy($orderBy);
        }

        return $criteria;
    }

    /**
     * @param mixed[] $pagination
     *
     * @return mixed[]
     */
    private function buildPagination(
        string $entityClassName,
        string $targetClassName,
        array $pagination,
        PersistentCollection $collection,
        Criteria $criteria,
        string|null $filterCriteriaEventName,
        mixed ...$resolve,
    ): array {
        $paginationFields = [
            'first' => 0,
            'last' => 0,
            'after' => 0,
            'before' => 0,
        ];

        // Pagination
        foreach ($pagination as $field => $value) {
            switch ($field) {
                case 'after':
                    $paginationFields[$field] = (int) base64_decode($value, true) + 1;
                    break;
                case 'before':
                    $paginationFields[$field] = (int) base64_decode($value, true);
                    break;
                default:
                    $paginationFields[$field] = $value;
                    $first                    = $value;
                    break;
            }
        }

        $itemCount = count($collection->matching($criteria));

        $offsetAndLimit = $this->calculateOffsetAndLimit($resolve[3]->fieldName, $entityClassName, $targetClassName, $paginationFields, $itemCount);
        if ($offsetAndLimit['offset']) {
            $criteria->setFirstResult($offsetAndLimit['offset']);
        }

        if ($offsetAndLimit['limit']) {
            $criteria->setMaxResults($offsetAndLimit['limit']);
        }

        /**
         * Fire the event dispatcher using the passed event name.
         */
        if ($filterCriteriaEventName) {
            $this->eventDispatcher->dispatch(
                new FilterCriteria(
                    $criteria,
                    $filterCriteriaEventName,
                    ...$resolve,
                ),
            );
        }

        $edgesAndCursors = $this->buildEdgesAndCursors($collection->matching($criteria), $offsetAndLimit, $itemCount);

        // Return entities
        return [
            'edges' => $edgesAndCursors['edges'],
            'totalCount' => $itemCount,
            'pageInfo' => [
                'endCursor' => $edgesAndCursors['cursors']['end'],
                'startCursor' => $edgesAndCursors['cursors']['start'],
                'hasNextPage' => $edgesAndCursors['cursors']['end'] !== $edgesAndCursors['cursors']['last'],
                'hasPreviousPage' => $edgesAndCursors['cursors']['first'] !== null
                    && $edgesAndCursors['cursors']['start'] !== $edgesAndCursors['cursors']['first'],
            ],
        ];
    }

    /**
     * @param array<string, int>     $offsetAndLimit
     * @param Collection<int, mixed> $items
     *
     * @return array<string, mixed>
     */
    protected function buildEdgesAndCursors(Collection $items, array $offsetAndLimit, int $itemCount): array
    {
        $edges   = [];
        $index   = 0;
        $cursors = [
            'first' => null,
            'last'  => base64_encode((string) 0),
            'start' => base64_encode((string) 0),
        ];

        foreach ($items as $item) {
            $cursors['last'] = base64_encode((string) ($index + $offsetAndLimit['offset']));

            $edges[] = [
                'node' => $item,
                'cursor' => $cursors['last'],
            ];

            if (! $cursors['first']) {
                $cursors['first'] = $cursors['last'];
            }

            $index++;
        }

        $endIndex       = $itemCount ? $itemCount - 1 : 0;
        $cursors['end'] = base64_encode((string) $endIndex);

        return [
            'cursors' => $cursors,
            'edges'   => $edges,
        ];
    }

    /**
     * @param array<string, int> $paginationFields
     *
     * @return array<string, int>
     */
    protected function calculateOffsetAndLimit(string $associationName, string $entityClassName, string $targetClassName, array $paginationFields, int $itemCount): array
    {
        $offset = 0;

        $limit            = $this->metadata[$targetClassName]['limit'];
        $associationLimit = $this->metadata[$entityClassName]['fields'][$associationName]['limit'] ?? null;

        if ($associationLimit) {
            $limit = $associationLimit;
        }

        if (! $limit) {
            $limit = $this->config->getLimit();
        }

        $adjustedLimit = $paginationFields['first'] ?: $paginationFields['last'] ?: $limit;

        if ($adjustedLimit < $limit) {
            $limit = $adjustedLimit;
        }

        if ($paginationFields['after']) {
            $offset = $paginationFields['after'];
        } elseif ($paginationFields['before']) {
            $offset = $paginationFields['before'] - $limit;
        }

        if ($offset < 0) {
            $limit += $offset;
            $offset = 0;
        }

        if ($paginationFields['last'] && ! $paginationFields['before']) {
            $offset = $itemCount - $paginationFields['last'];
        }

        return [
            'offset' => $offset,
            'limit'  => $limit,
        ];
    }
}
