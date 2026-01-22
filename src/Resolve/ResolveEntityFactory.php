<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

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
class ResolveEntityFactory
{
    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly Metadata $metadata,
        protected readonly PaginationService $paginationService,
    ) {
    }

    public function get(Entity $entity, string|null $eventName): Closure
    {
        return function ($objectValue, array $args, $context, ResolveInfo $info) use ($entity, $eventName) {
            $entityClass        = $entity->getEntityClass();
            $queryBuilderFilter = new QueryBuilderFilter();

            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select('entity')
                ->from($entityClass, 'entity');

            if (isset($args['filter'])) {
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
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args']['pagination'] ?? [],
        );

        // Get the limit for this entity
        $limit = $this->metadata[$entity->getEntityClass()]['limit'] ?: $this->config->getLimit();

        // Calculate offset and limit
        $offsetAndLimit = $this->paginationService->calculateOffsetAndLimit(
            $paginationFields,
            $limit,
        );

        /**
         * Fire the event dispatcher using the passed event name.
         * Include all resolve variables.
         */
        if ($eventName) {
            $this->eventDispatcher->dispatch(
                new QueryBuilderEvent(
                    $eventName,
                    $queryBuilder,
                    (int) $offsetAndLimit['offset'],
                    (int) $offsetAndLimit['limit'],
                    ...$resolve,
                ),
            );
        }

        if ($offsetAndLimit['offset']) {
            $queryBuilder->setFirstResult($offsetAndLimit['offset']);
        }

        if ($offsetAndLimit['limit']) {
            $queryBuilder->setMaxResults($offsetAndLimit['limit']);
        }

        // Get paginator to count items
        $paginator = new Paginator($queryBuilder->getQuery());
        $itemCount = $paginator->count();

        // Rebuild paginator if needed for 'last' without 'before'
        if ($paginationFields['last'] && ! $paginationFields['before']) {
            $offsetAndLimit['offset'] = $itemCount - $paginationFields['last'];
            $queryBuilder->setFirstResult($offsetAndLimit['offset']);
            $paginator = new Paginator($queryBuilder->getQuery());
        }

        // Get results
        $results = $paginator->getQuery()->getResult();

        // Build edges
        $edges = $this->paginationService->buildEdges($results, $offsetAndLimit['offset']);

        // Build cursors
        $cursors = $this->paginationService->buildCursors(
            $offsetAndLimit['offset'],
            $itemCount,
            count($results),
        );

        // Build final pagination response
        return $this->paginationService->buildPaginationResponse(
            $edges,
            $cursors,
            $itemCount,
        );
    }
}
