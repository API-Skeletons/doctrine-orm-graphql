<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria as CriteriaEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EventDispatcher;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ArrayObject;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use GraphQL\Type\Definition\ResolveInfo;

use function array_flip;
use function base64_decode;
use function base64_encode;
use function count;
use function in_array;

/**
 * Build a resolver for collections
 */
class ResolveCollectionFactory
{
    public function __construct(
        protected EntityManager $entityManager,
        protected Config $config,
        protected FieldResolver $fieldResolver,
        protected TypeContainer $typeContainer,
        protected EntityTypeContainer $entityTypeContainer,
        protected EventDispatcher $eventDispatcher,
        protected ArrayObject $metadata,
    ) {
    }

    public function get(Entity $entity): Closure
    {
        return function ($source, array $args, $context, ResolveInfo $info) use ($entity) {
            $fieldResolver = $this->fieldResolver;
            $collection    = $fieldResolver($source, $args, $context, $info);

            $defaultProxyClassNameResolver = new DefaultProxyClassNameResolver();
            $entityClassName               = $defaultProxyClassNameResolver->getClass($source);

            // If an alias map exists, check for an alias
            $targetCollectionName = $info->fieldName;
            if (in_array($info->fieldName, $this->entityTypeContainer->get($entityClassName)->getAliasMap())) {
                $targetCollectionName = array_flip($this->entityTypeContainer
                    ->get($entityClassName)->getAliasMap())[$info->fieldName] ?? $info->fieldName;
            }

            $targetClassName = (string) $this->entityManager->getMetadataFactory()
                ->getMetadataFor($entityClassName)
                ->getAssociationTargetClass($targetCollectionName);

            return $this->buildPagination(
                $entityClassName,
                $targetClassName,
                $args['pagination'] ?? [],
                $collection,
                $this->buildCriteria($args['filter'] ?? [], $entity),
                $this->metadata[$entityClassName]['fields'][$targetCollectionName]['criteriaEventName'],
                $source,
                $args,
                $context,
                $info,
            );
        };
    }

    /** @param mixed[] $filter */
    protected function buildCriteria(array $filter, Entity $entity): Criteria
    {
        $orderBy  = [];
        $criteria = Criteria::create();

        foreach ($filter as $field => $filters) {
            // Resolve aliases
            $field = array_flip($entity->getAliasMap())[$field] ?? $field;

            foreach ($filters as $filter => $value) {
                switch (Filters::from($filter)) {
                    case Filters::ISNULL:
                        $criteria->andWhere($criteria->expr()->$filter($field));
                        break;
                    case Filters::BETWEEN:
                        $criteria->andWhere($criteria->expr()->gte($field, $value['from']));
                        $criteria->andWhere($criteria->expr()->lte($field, $value['to']));
                        break;
                    case Filters::SORT:
                        $orderBy[$field] = $value;
                        break;
                    default:
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
    protected function buildPagination(
        string $entityClassName,
        string $targetClassName,
        array $pagination,
        PersistentCollection $collection,
        Criteria $criteria,
        string|null $criteriaEventName,
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
            $paginationFields[$field] = $value;

            if ($field === 'after') {
                $paginationFields[$field] = (int) base64_decode($value, true) + 1;
            }

            if ($field !== 'before') {
                continue;
            }

            $paginationFields[$field] = (int) base64_decode($value, true);
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
         *
         * @psalm-suppress TooManyArguments
         */
        if ($criteriaEventName) {
            $this->eventDispatcher->dispatch(
                $criteriaEventName,
                new CriteriaEvent(
                    $criteria,
                    $criteriaEventName,
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
    protected function calculateOffsetAndLimit(
        string $associationName,
        string $entityClassName,
        string $targetClassName,
        array $paginationFields,
        int $itemCount,
    ): array {
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
