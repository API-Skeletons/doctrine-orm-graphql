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
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;

use function array_flip;
use function count;
use function in_array;

/**
 * Build a resolver for collections
 */
class ResolveCollectionFactory
{
    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly Config $config,
        protected readonly FieldResolver $fieldResolver,
        protected readonly TypeContainer $typeContainer,
        protected readonly EntityTypeContainer $entityTypeContainer,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly Metadata $metadata,
        protected readonly PaginationService $paginationService,
        protected readonly QueryResultCache $queryResultCache,
    ) {
    }

    public function get(Entity $entity): Closure
    {
        return function ($source, array $args, $context, ResolveInfo $info) {
            $defaultProxyClassNameResolver = new DefaultProxyClassNameResolver();
            $entityClassName               = $defaultProxyClassNameResolver->getClass($source);

            // If an alias map exists, check for an alias
            $targetCollectionName = $info->fieldName;
            if (in_array($info->fieldName, $this->entityTypeContainer->get($entityClassName)->getExtractionMap())) {
                $targetCollectionName = array_flip($this->entityTypeContainer
                    ->get($entityClassName)->getExtractionMap())[$info->fieldName] ?? $info->fieldName;
            }

            $targetClassName = (string) $this->entityManager->getMetadataFactory()
                ->getMetadataFor($entityClassName)
                ->getAssociationTargetClass($targetCollectionName);

            // Get the target entity
            $targetEntity = $this->entityTypeContainer->get($targetClassName);

            // Get event name
            $eventName = $this->metadata[$entityClassName]['fields'][$targetCollectionName]['eventName'];

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

    /** @return mixed[] */
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
            $queryBuilderFilter->apply($resolve['args']['filter'], $queryBuilder, $entity);
        }

        // Decode pagination fields
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args']['pagination'] ?? [],
        );

        // Get the limit for this association
        $limit            = $this->metadata[$targetClassName]['limit'] ?? null;
        $associationLimit = $this->metadata[$entityClassName]['fields'][$associationName]['limit'] ?? null;

        if ($associationLimit) {
            $limit = $associationLimit;
        }

        if (! $limit) {
            $limit = $this->config->getLimit();
        }

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

        // Get results (with cache if enabled)
        $query = $paginator->getQuery();

        if ($this->config->getUseQueryResultCache()) {
            $cachedResults = $this->queryResultCache->get($query);
            if ($cachedResults !== null) {
                $results = $cachedResults;
            } else {
                $results = $query->getResult();
                $this->queryResultCache->set($query, $results);
            }
        } else {
            $results = $query->getResult();
        }

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
