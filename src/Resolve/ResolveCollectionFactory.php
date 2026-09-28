<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as QueryBuilderFilter;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\OrderByIdentifier;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;

use function array_flip;
use function assert;
use function class_exists;
use function count;
use function in_array;
use function is_object;
use function is_string;

/**
 * Build a resolver for collections
 */
final class ResolveCollectionFactory
{
    use OrderByIdentifier;

    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly Config $config,
        protected readonly FieldResolver $fieldResolver,
        protected readonly TypeContainer $typeContainer,
        protected readonly EntityTypeContainer $entityTypeContainer,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly PaginationService $paginationService,
        protected readonly QueryResultCache $queryResultCache,
    ) {
    }

    public function get(Entity $entity): Closure
    {
        return function (mixed $source, array $args, mixed $context, ResolveInfo $info) {
            assert(is_object($source));
            $defaultProxyClassNameResolver = new DefaultProxyClassNameResolver();
            $entityClassName               = $defaultProxyClassNameResolver->getClass($source);

            // If an alias map exists, check for an alias
            $targetCollectionName = $info->fieldName;
            /** @psalm-suppress MixedMethodCall, MixedArgument */
            if (in_array($info->fieldName, $this->entityTypeContainer->get($entityClassName)->getExtractionMap())) {
                $targetCollectionName = array_flip($this->entityTypeContainer
                    ->get($entityClassName)->getExtractionMap())[$info->fieldName] ?? $info->fieldName;
            }

            /** @psalm-suppress MixedArgumentTypeCoercion */
            $targetClassName = $this->entityManager->getMetadataFactory()
                ->getMetadataFor($entityClassName)
                ->getAssociationTargetClass($targetCollectionName);

            // Get the target entity
            $targetEntity = $this->entityTypeContainer->get($targetClassName);
            assert($targetEntity instanceof Entity);

            assert(is_string($targetCollectionName));

            // Get event name
            $sourceEntity = $this->entityTypeContainer->get($entityClassName);
            assert($sourceEntity instanceof Entity);
            $eventName = $sourceEntity->getEntityMetadata()->associations[$targetCollectionName]->eventName ?? null;

            return $this->buildPagination(
                entity: $targetEntity,
                entityClassName: $entityClassName,
                targetClassName: $targetClassName,
                associationName: $targetCollectionName,
                source: $source,
                eventName: $eventName,
                objectValue: $source,
                args: $args,
                context: $context,
                info: $info,
            );
        };
    }

    /**
     * @return mixed[]
     *
     * @psalm-suppress MixedOperand
     */
    protected function buildPagination(
        Entity $entity,
        string $entityClassName,
        string $targetClassName,
        string $associationName,
        mixed $source,
        string|null $eventName,
        mixed ...$resolve,
    ): array {
        // Get the association metadata
        $sourceMetadata = $this->entityManager->getClassMetadata($entityClassName);
        $association    = $sourceMetadata->getAssociationMapping($associationName);

        // Build QueryBuilder for the association
        assert(class_exists($targetClassName));
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('entity')
            ->from($targetClassName, 'entity');

        // Handle different association types
        if (isset($association['joinTable'])) {
            // Many-to-many relationship (owning side with join table)
            // Use Doctrine's association mapping instead of manual join table handling
            $queryBuilder->innerJoin($entityClassName, 'source', 'WITH', ':source MEMBER OF source.' . $associationName);
            $queryBuilder->setParameter('source', $source);
        } elseif (isset($association['mappedBy'])) {
            // One-to-many: target entity has the foreign key
            $queryBuilder->where('entity.' . $association['mappedBy'] . ' = :source');
            $queryBuilder->setParameter('source', $source);
            // @codeCoverageIgnoreStart
        } elseif (isset($association['inversedBy'])) {
            // Many-to-one from the owning side (less common for collections)
            // This is defensively handled here for completeness
            $queryBuilder->innerJoin($entityClassName, 'source', 'WITH', 'source.' . $associationName . ' = entity');
            $queryBuilder->where('source = :source');
            $queryBuilder->setParameter('source', $source);
            // @codeCoverageIgnoreEnd
        }

        // Apply filters using QueryBuilder
        $queryBuilderFilter = new QueryBuilderFilter();
        if (isset($resolve['args']['filter'])) {
            /** @psalm-suppress MixedArgument */
            $queryBuilderFilter->apply($resolve['args']['filter'], $queryBuilder, $entity);
        }

        // Decode pagination fields
        /** @psalm-suppress MixedArgument */
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args'] ?? [],
        );

        // Get the limit for this association: the association's own limit,
        // else the target entity's, else the configured limit
        $sourceEntity = $this->entityTypeContainer->get($entityClassName);
        assert($sourceEntity instanceof Entity);
        $associationLimit = $sourceEntity->getEntityMetadata()->associations[$associationName]->limit ?? null;

        $limit = $associationLimit !== null && $associationLimit !== 0
            ? $associationLimit
            : $entity->getEntityMetadata()->limit;

        if ($limit === 0) {
            $limit = $this->config->getLimit();
        }

        /**
         * Fire the event dispatcher using the passed event name.
         * Include all resolve variables.
         *
         * The event is dispatched before the rows are counted so a listener
         * may modify the QueryBuilder.  It therefore carries the requested
         * offset and limit rather than the final ones.
         */
        if ($eventName !== null) {
            /** @psalm-suppress MixedArgument */
            $requested = $this->paginationService->calculateRequestedOffsetAndLimit(
                $paginationFields,
                $limit,
            );

            /** @psalm-suppress MixedArgument */
            $this->eventDispatcher->dispatch(
                new QueryBuilderEvent(
                    $eventName,
                    $queryBuilder,
                    $requested['offset'],
                    $requested['limit'],
                    ...$resolve,
                ),
            );
        }

        // The rows must be counted before the offset and limit can be resolved
        // Paginator is deprecated as of ORM 3.7 in favour of OffsetPaginator, which
        // does not exist in ORM 2.x or ORM < 3.7. Keep Paginator until those are dropped.
        /** @psalm-suppress DeprecatedClass */
        $itemCount = (new Paginator($queryBuilder->getQuery()))->count();

        // Calculate offset and limit
        /** @psalm-suppress MixedArgument */
        $offsetAndLimit = $this->paginationService->calculateOffsetAndLimit(
            $paginationFields,
            $limit,
            $itemCount,
        );

        // A limit of zero cannot match a row so the query is not executed
        $results = [];

        if ($offsetAndLimit['limit'] > 0) {
            $this->orderByIdentifier($queryBuilder);
            $queryBuilder->setFirstResult($offsetAndLimit['offset']);
            $queryBuilder->setMaxResults($offsetAndLimit['limit']);

            /** @psalm-suppress MixedAssignment */
            $results = $this->getResults($queryBuilder);
        }

        // Build edges
        /** @psalm-suppress PossiblyInvalidArgument */
        $edges = $this->paginationService->buildEdges($results, $offsetAndLimit['offset']);

        // Build cursors
        /** @psalm-suppress MixedArgument */
        $cursors = $this->paginationService->buildCursors(
            $offsetAndLimit['offset'],
            count($results),
        );

        // Build final pagination response
        return $this->paginationService->buildPaginationResponse(
            $edges,
            $cursors,
            $itemCount,
            $offsetAndLimit['offset'],
        );
    }

    /**
     * Fetch the rows for the QueryBuilder, using the query result cache when enabled
     *
     * @return mixed[]
     */
    private function getResults(QueryBuilder $queryBuilder): array
    {
        $query = $queryBuilder->getQuery();

        if (! $this->config->getUseQueryResultCache()) {
            /** @psalm-suppress MixedReturnStatement */
            return $query->getResult();
        }

        $cachedResults = $this->queryResultCache->get($query);

        if ($cachedResults !== null) {
            return $cachedResults;
        }

        /** @psalm-suppress MixedAssignment */
        $results = $query->getResult();
        /** @psalm-suppress MixedArgument */
        $this->queryResultCache->set($query, $results);

        return $results;
    }
}
