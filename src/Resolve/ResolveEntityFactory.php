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
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;

use function assert;

/**
 * Build a resolver for entities
 */
final class ResolveEntityFactory
{
    use OrderByIdentifier;

    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EventDispatcher $eventDispatcher,
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

    /** @return mixed[] */
    public function buildPagination(
        Entity $entity,
        QueryBuilder $queryBuilder,
        string|null $eventName,
        mixed ...$resolve,
    ): array {
        // Decode pagination fields
        /** @psalm-suppress MixedArgument */
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args'] ?? [],
        );

        // Get the limit for this entity
        $limit = $entity->getEntityMetadata()->limit ?: $this->config->getLimit();

        /**
         * Fire the event dispatcher using the passed event name.
         * Include all resolve variables.
         *
         * The event is dispatched before the rows are counted so a listener
         * may modify the QueryBuilder.  It therefore carries the requested
         * offset and limit rather than the final ones.
         */
        if ($eventName !== null) {
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

        $info = $resolve['info'] ?? null;
        assert($info instanceof ResolveInfo || $info === null);

        return $this->paginationService->paginate(
            $paginationFields,
            $limit,
            $info,
            // Paginator is deprecated as of ORM 3.7 in favour of OffsetPaginator, which
            // does not exist in ORM 2.x or ORM < 3.7. Keep Paginator until those are dropped.
            /** @psalm-suppress DeprecatedClass */
            static fn (): int => (new Paginator($queryBuilder->getQuery()))->count(),
            function (int $offset, int $limit) use ($queryBuilder): array {
                $this->orderByIdentifier($queryBuilder);
                $queryBuilder->setFirstResult($offset);
                $queryBuilder->setMaxResults($limit);

                return $this->getResults($queryBuilder);
            },
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
