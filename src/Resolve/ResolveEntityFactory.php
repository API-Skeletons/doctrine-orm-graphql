<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as QueryBuilderFilter;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;

use function count;

/**
 * Build a resolver for entities
 */
final class ResolveEntityFactory
{
    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly Metadata $metadata,
        protected readonly PaginationService $paginationService,
        protected readonly QueryResultCache $queryResultCache,
    ) {
    }

    public function get(Entity $entity, string|null $eventName): Closure
    {
        return function (mixed $objectValue, array $args, mixed $context, ResolveInfo $info) use ($entity, $eventName) {
            $entityClass        = $entity->getEntityClass();
            $queryBuilderFilter = new QueryBuilderFilter();

            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select('entity')
                ->from($entityClass, 'entity');

            if (isset($args['filter'])) {
                /** @psalm-suppress MixedArgument */
                $queryBuilderFilter->apply($args['filter'], $queryBuilder, $entity);
            }

            return $this->buildPagination(
                entity: $entity,
                queryBuilder: $queryBuilder,
                eventName: $eventName,
                objectValue: $objectValue,
                args: $args,
                context: $context,
                info: $info,
            );
        };
    }

    /**
     * @return mixed[]
     *
     * @psalm-suppress MixedArgument, MixedArrayAccess, MixedAssignment
     */
    public function buildPagination(
        Entity $entity,
        QueryBuilder $queryBuilder,
        string|null $eventName,
        mixed ...$resolve,
    ): array {
        // Decode pagination fields
        /** @psalm-suppress MixedArgument, MixedArrayAccess */
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args']['pagination'] ?? [],
        );

        // Get the limit for this entity
        /** @psalm-suppress MixedAssignment, MixedArrayAccess */
        $limit = $this->metadata[$entity->getEntityClass()]['limit'] ?: $this->config->getLimit();

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
            $queryBuilder->setFirstResult($offsetAndLimit['offset']);
            $queryBuilder->setMaxResults($offsetAndLimit['limit']);

            /** @psalm-suppress MixedAssignment */
            $results = $this->getResults($queryBuilder);
        }

        // Build edges
        /** @psalm-suppress PossiblyInvalidArgument, MixedArgument */
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

        /** @psalm-suppress MixedReturnStatement */
        return $results;
    }
}
