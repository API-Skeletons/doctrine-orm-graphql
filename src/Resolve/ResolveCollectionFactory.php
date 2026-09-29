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
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\EventDispatcher;
use Throwable;

use function array_chunk;
use function array_flip;
use function array_slice;
use function array_sum;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function in_array;
use function is_int;
use function is_object;
use function is_string;
use function serialize;

/**
 * Build a resolver for collections
 */
final class ResolveCollectionFactory
{
    use OrderByIdentifier;

    /** The most sources matched by one IN list */
    private const int CHUNK_SIZE = 1000;

    /**
     * Batches waiting to be loaded, keyed by the field and its arguments.  A
     * batch is removed when it is loaded, so nothing outlives an execution.
     *
     * @var array<string, CollectionBatch>
     */
    private array $batches = [];

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

            $sourceIdentifier = $this->getBatchableIdentifier($entityClassName, $targetCollectionName, $source);

            if ($eventName === null && $this->config->getBatchAssociations() && $sourceIdentifier !== null) {
                return $this->deferToBatch(
                    $targetEntity,
                    $entityClassName,
                    $targetClassName,
                    $targetCollectionName,
                    $source,
                    $sourceIdentifier,
                    $args,
                );
            }

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
        assert(class_exists($entityClassName));
        assert(class_exists($targetClassName));

        $queryBuilder = $this->createQueryBuilder($targetClassName);
        $this->restrictToSource($queryBuilder, $entityClassName, $associationName, $source);
        /** @psalm-suppress MixedArgument */
        $this->applyFilters($queryBuilder, $resolve['args'] ?? [], $entity);

        // Decode pagination fields
        /** @psalm-suppress MixedArgument */
        $paginationFields = $this->paginationService->decodePaginationFields(
            $resolve['args'] ?? [],
        );

        $limit = $this->getLimit($entity, $entityClassName, $associationName);

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

        $info = $resolve['info'] ?? null;
        assert($info instanceof ResolveInfo || $info === null);

        /** @psalm-suppress MixedArgument */
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
     * Defer a source's collection to the batch for its field and arguments
     *
     * @param class-string            $sourceClassName
     * @param class-string            $targetClassName
     * @param array<array-key, mixed> $args
     */
    private function deferToBatch(
        Entity $targetEntity,
        string $sourceClassName,
        string $targetClassName,
        string $associationName,
        object $source,
        int|string $sourceIdentifier,
        array $args,
    ): Deferred {
        $key = $sourceClassName . "\0" . $associationName . "\0" . serialize($args);

        $batch = $this->batches[$key] ??= new CollectionBatch(
            $targetEntity,
            $sourceClassName,
            $targetClassName,
            $associationName,
            $args,
        );
        $batch->add($source, $sourceIdentifier);

        return new Deferred(function () use ($key, $batch, $source): array {
            if (! $batch->isLoaded()) {
                // Sources registered from now on start a new batch
                if (($this->batches[$key] ?? null) === $batch) {
                    unset($this->batches[$key]);
                }

                // An error loading the batch is the error of every source's field,
                // as it would be for per-source queries
                try {
                    $this->loadBatch($batch);
                } catch (Throwable $error) {
                    $batch->setError($error);
                }
            }

            return $batch->getResult($source);
        });
    }

    /**
     * Resolve the connection of every source in a batch.  One query counts
     * the rows of every source.  When the rows number no more than the batch
     * limit, one query fetches them all and each source's page is taken from
     * its own rows; otherwise each source's page is queried.
     */
    private function loadBatch(CollectionBatch $batch): void
    {
        $batch->markLoaded();

        $paginationFields = $this->paginationService->decodePaginationFields($batch->args);
        $limit            = $this->getLimit($batch->targetEntity, $batch->sourceClassName, $batch->associationName);
        $itemCounts       = $this->countBatch($batch);

        // Each source's page, from its own number of rows
        $pages     = [];
        $needsRows = false;
        foreach ($batch->getSources() as [, $identifier]) {
            $page = $this->paginationService->calculateOffsetAndLimit(
                $paginationFields,
                $limit,
                $itemCounts[(string) $identifier] ?? 0,
            );

            $pages[(string) $identifier] = $page;
            $needsRows                   = $needsRows || $page['limit'] > 0;
        }

        $pageRows = $needsRows && array_sum($itemCounts) <= $this->config->getBatchLimit()
            ? $this->fetchBatchPages($batch, $pages)
            : null;

        foreach ($batch->getSources() as [$source, $identifier]) {
            $page = $pages[(string) $identifier];

            $results = [];

            if ($pageRows !== null) {
                $results = $pageRows[(string) $identifier] ?? [];
            } elseif ($page['limit'] > 0) {
                $queryBuilder = $this->createQueryBuilder($batch->targetClassName);
                $this->restrictToSource($queryBuilder, $batch->sourceClassName, $batch->associationName, $source);
                $this->applyFilters($queryBuilder, $batch->args, $batch->targetEntity);
                $this->orderByIdentifier($queryBuilder);
                $queryBuilder->setFirstResult($page['offset']);
                $queryBuilder->setMaxResults($page['limit']);

                $results = $this->getResults($queryBuilder);
            }

            $batch->setResult($source, $this->buildResponse($results, $page['offset'], $itemCounts[(string) $identifier] ?? 0));
        }
    }

    /**
     * Fetch the page of every source in a batch.  The rows of every source are
     * fetched in order with one query per chunk of sources; the order is the
     * order each source's own query would have, so each source's page is a
     * slice of its own rows.
     *
     * @param array<string, array{offset: int, limit: int}> $pages The page of each source, by identifier
     *
     * @return array<string, list<mixed>> The rows of each source's page, by identifier
     */
    private function fetchBatchPages(CollectionBatch $batch, array $pages): array
    {
        $association = $this->entityManager->getClassMetadata($batch->sourceClassName)
            ->getAssociationMapping($batch->associationName);

        if ($association['type'] === ClassMetadata::MANY_TO_MANY) {
            return $this->fetchBatchManyToManyPages($batch, $pages);
        }

        $rowsBySource = [];

        foreach (array_chunk($this->getBatchIdentifiers($batch), self::CHUNK_SIZE) as $chunk) {
            $queryBuilder = $this->createBatchQueryBuilder($batch, $chunk, $parent);
            $queryBuilder->addSelect($parent['select'] . ' AS parent');

            /** @var array<array{0: object, parent: int|string}> $rows */
            $rows = $this->getResults($queryBuilder);
            foreach ($rows as $row) {
                $rowsBySource[(string) $row['parent']][] = $row[0];
            }
        }

        return $this->slicePages($rowsBySource, $pages);
    }

    /**
     * Take each source's page from its rows
     *
     * @param array<string, list<T>>                        $rowsBySource
     * @param array<string, array{offset: int, limit: int}> $pages
     *
     * @return array<string, list<T>>
     *
     * @template T
     */
    private function slicePages(array $rowsBySource, array $pages): array
    {
        $pageRows = [];
        foreach ($pages as $identifier => $page) {
            $pageRows[$identifier] = $page['limit'] > 0
                ? array_slice($rowsBySource[$identifier] ?? [], $page['offset'], $page['limit'])
                : [];
        }

        return $pageRows;
    }

    /**
     * A target of a many-to-many collection may belong to several sources.
     * Doctrine returns an entity once however many rows it is in, so the
     * source and target identifiers are fetched as scalars, each source's page
     * is taken from them, and only the targets on a page are loaded.
     *
     * @param array<string, array{offset: int, limit: int}> $pages The page of each source, by identifier
     *
     * @return array<string, list<mixed>> The rows of each source's page, by identifier
     */
    private function fetchBatchManyToManyPages(CollectionBatch $batch, array $pages): array
    {
        $targetMetadata = $this->entityManager->getClassMetadata($batch->targetClassName);
        $targetId       = $targetMetadata->getSingleIdentifierFieldName();

        $pairs = [];
        foreach (array_chunk($this->getBatchIdentifiers($batch), self::CHUNK_SIZE) as $chunk) {
            $queryBuilder = $this->createBatchQueryBuilder($batch, $chunk, $parent);
            $queryBuilder
                ->select($parent['select'] . ' AS parent')
                ->addSelect('entity.' . $targetId . ' AS target');

            /** @var array<array{parent: int|string, target: int|string}> $rows */
            $rows = $queryBuilder->getQuery()->getScalarResult();
            foreach ($rows as $row) {
                $pairs[(string) $row['parent']][] = $row['target'];
            }
        }

        $pageTargetIds = $this->slicePages($pairs, $pages);

        // Load each target on a page once
        $targetIds = [];
        foreach ($pageTargetIds as $ids) {
            foreach ($ids as $id) {
                $targetIds[(string) $id] = $id;
            }
        }

        $targets = [];
        foreach (array_chunk(array_values($targetIds), self::CHUNK_SIZE) as $chunk) {
            /** @var list<object> $entities */
            $entities = $this->createQueryBuilder($batch->targetClassName)
                ->where('entity.' . $targetId . ' IN (:targets)')
                ->setParameter('targets', $chunk)
                ->getQuery()
                ->getResult();

            foreach ($entities as $entity) {
                /** @psalm-suppress MixedArrayOffset An identifier may be of any type */
                $targets[(string) $targetMetadata->getIdentifierValues($entity)[$targetId]] = $entity;
            }
        }

        $pageRows = [];
        foreach ($pageTargetIds as $identifier => $ids) {
            $pageRows[$identifier] = [];
            foreach ($ids as $id) {
                $pageRows[$identifier][] = $targets[(string) $id];
            }
        }

        return $pageRows;
    }

    /**
     * A query for the rows of a chunk of a batch's sources, filtered and
     * ordered.  $parent receives the expressions for each row's source.
     *
     * @param list<int|string>                            $identifiers
     * @param array{select: string, groupBy: string}|null $parent
     *
     * @param-out array{select: string, groupBy: string} $parent
     */
    private function createBatchQueryBuilder(CollectionBatch $batch, array $identifiers, array|null &$parent): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($batch->targetClassName);
        $parent       = $this->restrictToSources(
            $queryBuilder,
            $batch->sourceClassName,
            $batch->associationName,
            $identifiers,
        );
        $this->applyFilters($queryBuilder, $batch->args, $batch->targetEntity);
        $this->orderByIdentifier($queryBuilder);

        return $queryBuilder;
    }

    /**
     * The distinct identifiers of the sources in a batch
     *
     * @return list<int|string>
     */
    private function getBatchIdentifiers(CollectionBatch $batch): array
    {
        $identifiers = [];
        foreach ($batch->getSources() as [, $identifier]) {
            $identifiers[(string) $identifier] = $identifier;
        }

        return array_values($identifiers);
    }

    /**
     * Count the rows of every source in a batch with one query per chunk of
     * sources
     *
     * @return array<string, int> The number of rows, by source identifier
     */
    private function countBatch(CollectionBatch $batch): array
    {
        $itemCounts = [];

        foreach (array_chunk($this->getBatchIdentifiers($batch), self::CHUNK_SIZE) as $chunk) {
            $queryBuilder = $this->createQueryBuilder($batch->targetClassName);
            $parent       = $this->restrictToSources(
                $queryBuilder,
                $batch->sourceClassName,
                $batch->associationName,
                $chunk,
            );
            $this->applyFilters($queryBuilder, $batch->args, $batch->targetEntity);

            // An aggregate query is not ordered
            $queryBuilder->resetDQLPart('orderBy');
            $queryBuilder
                ->select($parent['select'] . ' AS parent')
                ->addSelect('COUNT(DISTINCT entity) AS total')
                ->groupBy($parent['groupBy']);

            /** @var array<array{parent: int|string, total: int|string}> $rows */
            $rows = $queryBuilder->getQuery()->getScalarResult();
            foreach ($rows as $row) {
                $itemCounts[(string) $row['parent']] = (int) $row['total'];
            }
        }

        return $itemCounts;
    }

    /**
     * The source's identifier if its collection can be batched: the source
     * and target have a single identifier and the source's is an int or a
     * string.  A collection is always one-to-many or many-to-many.
     *
     * @param class-string $sourceClassName
     */
    private function getBatchableIdentifier(string $sourceClassName, string $associationName, object $source): int|string|null
    {
        $sourceMetadata = $this->entityManager->getClassMetadata($sourceClassName);
        $targetMetadata = $this->entityManager->getClassMetadata($sourceMetadata->getAssociationTargetClass($associationName));

        if (count($sourceMetadata->getIdentifierFieldNames()) !== 1 || count($targetMetadata->getIdentifierFieldNames()) !== 1) {
            return null;
        }

        /** @psalm-suppress MixedAssignment An identifier may be of any type */
        $identifier = $sourceMetadata->getIdentifierValues($source)[$sourceMetadata->getSingleIdentifierFieldName()] ?? null;

        return is_int($identifier) || is_string($identifier) ? $identifier : null;
    }

    /** @param class-string $targetClassName */
    private function createQueryBuilder(string $targetClassName): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('entity')
            ->from($targetClassName, 'entity');
    }

    /**
     * Restrict a query to one source's collection
     *
     * @param class-string $sourceClassName
     */
    private function restrictToSource(
        QueryBuilder $queryBuilder,
        string $sourceClassName,
        string $associationName,
        mixed $source,
    ): void {
        $association = $this->entityManager->getClassMetadata($sourceClassName)->getAssociationMapping($associationName);

        if ($association['type'] === ClassMetadata::ONE_TO_MANY) {
            // One-to-many: the target entity holds the foreign key
            $mappedBy = $association['mappedBy'];
            assert(is_string($mappedBy));
            $queryBuilder->where('entity.' . $mappedBy . ' = :source');
        } else {
            // Many-to-many, from either side: the target must be a member of
            // this source's collection
            $queryBuilder
                ->innerJoin($sourceClassName, 'source', 'WITH', 'entity MEMBER OF source.' . $associationName)
                ->where('source = :source');
        }

        $queryBuilder->setParameter('source', $source);
    }

    /**
     * Restrict a query to the collections of several sources, identified by
     * their identifiers.  Returns the expression selecting each row's source
     * identifier and the path to group by it.
     *
     * @param class-string     $sourceClassName
     * @param list<int|string> $identifiers
     *
     * @return array{select: string, groupBy: string}
     */
    private function restrictToSources(
        QueryBuilder $queryBuilder,
        string $sourceClassName,
        string $associationName,
        array $identifiers,
    ): array {
        $sourceMetadata = $this->entityManager->getClassMetadata($sourceClassName);
        $association    = $sourceMetadata->getAssociationMapping($associationName);

        if ($association['type'] === ClassMetadata::ONE_TO_MANY) {
            $mappedBy = $association['mappedBy'];
            assert(is_string($mappedBy));
            $path = 'entity.' . $mappedBy;
            $queryBuilder->where($path . ' IN (:sources)');
            $parent = ['select' => 'IDENTITY(' . $path . ')', 'groupBy' => $path];
        } else {
            $path = 'source.' . $sourceMetadata->getSingleIdentifierFieldName();
            $queryBuilder
                ->innerJoin($sourceClassName, 'source', 'WITH', 'entity MEMBER OF source.' . $associationName)
                ->where($path . ' IN (:sources)');
            $parent = ['select' => $path, 'groupBy' => $path];
        }

        $queryBuilder->setParameter('sources', $identifiers);

        return $parent;
    }

    /** @param array<array-key, mixed> $args */
    private function applyFilters(QueryBuilder $queryBuilder, array $args, Entity $entity): void
    {
        if (! isset($args['filter'])) {
            return;
        }

        /** @psalm-suppress MixedArgument */
        (new QueryBuilderFilter())->apply($args['filter'], $queryBuilder, $entity);
    }

    /**
     * The limit of an association: its own, else the target entity's, else
     * the configured limit
     *
     * @param class-string $sourceClassName
     */
    private function getLimit(Entity $targetEntity, string $sourceClassName, string $associationName): int
    {
        $sourceEntity = $this->entityTypeContainer->get($sourceClassName);
        assert($sourceEntity instanceof Entity);
        $associationLimit = $sourceEntity->getEntityMetadata()->associations[$associationName]->limit ?? null;

        $limit = $associationLimit !== null && $associationLimit !== 0
            ? $associationLimit
            : $targetEntity->getEntityMetadata()->limit;

        return $limit === 0 ? $this->config->getLimit() : $limit;
    }

    /**
     * @param mixed[] $results
     *
     * @return mixed[]
     */
    private function buildResponse(array $results, int $offset, int $itemCount): array
    {
        return $this->paginationService->buildPaginationResponse(
            $this->paginationService->buildEdges(array_values($results), $offset),
            $this->paginationService->buildCursors($offset, count($results)),
            $itemCount,
            $offset,
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
