============
Architecture
============

This document provides a deep dive into the library's architecture, explaining how components interact and the design decisions behind them.

Overview
========

The library follows a service-oriented architecture with dependency injection, lazy initialization, and event-driven customization. Key architectural patterns include:

- **Container Pattern**: PSR-11 compliant dependency injection
- **Lazy Initialization**: PHP 8.4 Lazy Ghost objects for deferred service loading
- **Factory Pattern**: Factories for creating GraphQL types, resolvers, and filters
- **Event-Driven**: PSR-14 event dispatching for extensibility
- **Strategy Pattern**: Hydration strategies for data extraction

Component Diagram
=================

::

    ┌─────────────────────────────────────────────────────────┐
    │                       Driver                                 │
    │  (extends Container, uses Services trait)                    │
    │                                                              │
    │  Public API: connection(), type(), filter(), resolve()       │
    └───────────────────┬─────────────────────────────────────┘
                        │
           ┌────────────┴────────────┐
           │                           │
    ┌──────▼───────┐          ┌─────▼──────┐
    │ TypeContainer │          │   Config    │
    └──────┬───────┘          └─────┬──────┘
           │                          │
    ┌──────▼──────────────────┐    │
    │ EntityTypeContainer     │◄───┘
    │ (Lazy Ghost objects)    │
    └──────┬──────────────────┘
           │
    ┌──────▼──────────────────┐
    │  MetadataFactory          │
    │  (Scans attributes)       │
    └──────┬──────────────────┘
           │
    ┌──────▼──────────────────┐
    │     Metadata              │
    │  (ArrayObject storage)    │
    └─────────────────────────┘

    Query Resolution Flow:
    ======================

    GraphQL Query
         │
         ▼
    ResolveEntityFactory  ──►  QueryBuilder
         │                         │
         ▼                         ▼
    PaginationService         FilterFactory
         │                         │
         ▼                         ▼
    Doctrine Paginator       Database Query
         │                         │
         ▼                         ▼
    HydratorContainer         Result Set
         │                         │
         ▼                         ▼
    GraphQL Response          Extracted Data

Container System
================

The Container Class
-------------------

Located at ``src/Container.php``, this PSR-11 compliant container provides:

.. code-block:: php

    class Container implements ContainerInterface
    {
        private array $entries = [];

        public function set(string $id, mixed $value): static
        {
            // Store services/types by lowercase ID
            $this->entries[strtolower($id)] = $value;
            return $this;
        }

        public function get(string $id): mixed
        {
            // Lazy initialization via closures
            if ($this->entries[strtolower($id)] instanceof Closure) {
                $this->entries[strtolower($id)] =
                    $this->entries[strtolower($id)]($this);
            }
            return $this->entries[strtolower($id)];
        }

        public function has(string $id): bool
        {
            return isset($this->entries[strtolower($id)]);
        }
    }

**Design Decisions:**

1. **Case-Insensitive Keys**: IDs are stored lowercase to prevent issues with class name casing
2. **Lazy Resolution**: Closures are resolved on first access
3. **Chainable API**: ``set()`` returns ``$this`` for fluent configuration

The Driver Class
----------------

The ``Driver`` extends ``Container`` and uses the ``Services`` trait:

.. code-block:: php

    class Driver extends Container
    {
        use Services;

        public function connection(string $id, ?string $eventName = null): ObjectType
        {
            $objectType = $this->type($id, $eventName);
            return $this->get(Type\TypeContainer::class)
                ->build(Type\Connection::class, $objectType->name, $objectType);
        }

        public function type(string $id, ?string $eventName = null): mixed
        {
            // Try EntityTypeContainer first
            if ($this->get(Type\Entity\EntityTypeContainer::class)->has($id)) {
                return $this->get(Type\Entity\EntityTypeContainer::class)
                    ->get($id, $eventName)
                    ->getObjectType();
            }

            // Fall back to TypeContainer
            if ($this->get(Type\TypeContainer::class)->has($id)) {
                return $this->get(Type\TypeContainer::class)->get($id);
            }

            // Suggest similar types if not found
            throw new TypeNotFoundException(...);
        }
    }

Services Trait
--------------

The ``Services`` trait (``src/Services.php``) registers all core services:

.. code-block:: php

    trait Services
    {
        public function __construct(
            readonly EntityManager $entityManager,
            readonly Config|null $config = null,
            readonly array $metadataArray = [],
        ) {
            $this
                ->set(EntityManager::class, $entityManager)
                ->set(Config::class, fn() => $config ?? new Config())
                ->set(EventDispatcher::class, fn() => new EventDispatcher())

                // Lazy Ghost for EntityTypeContainer
                ->set(Type\Entity\EntityTypeContainer::class,
                    (new ReflectionClass(Type\Entity\EntityTypeContainer::class))
                        ->newLazyGhost(fn($object) => $object->__construct($this))
                )

                // Lazy Ghosts for resolve factories
                ->set(Resolve\ResolveEntityFactory::class, ...)
                ->set(Resolve\ResolveCollectionFactory::class, ...)

                // Other services...
                ->set(Pagination\PaginationService::class, ...)
                ->set(Filter\FilterFactory::class, ...)
                ->set(Hydrator\HydratorContainer::class, ...);
        }
    }

**Lazy Ghost Pattern:**

PHP 8.4 introduces Lazy Ghost objects that defer initialization until first property access:

.. code-block:: php

    // Object created but constructor not called
    $lazyObject = (new ReflectionClass(MyClass::class))
        ->newLazyGhost(function ($object) {
            // Constructor called on first property access
            $object->__construct($dependencies...);
        });

**Benefits:**

- Reduced memory footprint (objects not initialized unless used)
- Circular dependency resolution
- Faster Driver instantiation

Type System
===========

EntityTypeContainer
-------------------

Located at ``src/Type/Entity/EntityTypeContainer.php``:

.. code-block:: php

    class EntityTypeContainer
    {
        private array $entities = [];

        public function __construct(private Driver $driver) {}

        public function get(string $entityClass, ?string $eventName = null): Entity
        {
            $key = $this->normalizeKey($entityClass);

            if (!isset($this->entities[$key])) {
                // Create Entity instance (internal representation)
                $this->entities[$key] = new Entity(
                    $entityClass,
                    $this->driver,
                    $eventName
                );
            }

            return $this->entities[$key];
        }

        public function has(string $entityClass): bool
        {
            $metadata = $this->driver->get(Metadata::class);
            return isset($metadata[$entityClass]);
        }
    }

**Entity Class:**

The ``Entity`` class (``src/Type/Entity/Entity.php``) is an internal representation that wraps:

- Entity metadata (fields, associations, limits)
- ObjectType (GraphQL type definition)
- Extraction map (field aliasing)
- Hydrator configuration

.. code-block:: php

    class Entity
    {
        private ?ObjectType $objectType = null;
        private ?ExtractionMap $extractionMap = null;

        public function __construct(
            private string $entityClass,
            private Driver $driver,
            private ?string $eventName = null,
        ) {}

        public function getObjectType(): ObjectType
        {
            if ($this->objectType === null) {
                $this->objectType = $this->buildObjectType();
            }
            return $this->objectType;
        }

        private function buildObjectType(): ObjectType
        {
            $definition = new Definition(
                $this->entityClass,
                $this->driver,
            );

            // Fire EntityDefinition event for customization
            $event = new EntityDefinitionEvent(
                $this->eventName ?? $this->entityClass,
                $definition,
            );
            $this->driver->get(EventDispatcher::class)->dispatch($event);

            return $definition->build();
        }
    }

TypeContainer
-------------

Located at ``src/Type/TypeContainer.php``:

.. code-block:: php

    class TypeContainer extends Container
    {
        public function __construct()
        {
            parent::__construct();

            // Register built-in types
            $this->set('datetime', new Type\DateTime());
            $this->set('date', new Type\Date());
            $this->set('time', new Type\Time());
            $this->set('blob', new Type\Blob());
            $this->set('json', new Type\Json());
            $this->set('pagination', new Type\Pagination());
            // ... more types
        }

        public function build(string $buildableClass, string $id, mixed ...$args): mixed
        {
            // Create types that depend on other types
            $buildable = new $buildableClass(...$args);

            if (!$buildable instanceof Buildable) {
                throw new Exception('...');
            }

            $type = $buildable->build();
            $this->set($id, $type);

            return $type;
        }
    }

**Buildable Interface:**

Types that depend on other types implement ``Buildable``:

.. code-block:: php

    interface Buildable
    {
        public function build(): Type;
    }

    class Connection implements Buildable
    {
        public function __construct(private ObjectType $entityType) {}

        public function build(): ObjectType
        {
            return new ObjectType([
                'name' => $this->entityType->name . 'Connection',
                'fields' => [
                    'edges' => Type::listOf(new Node($this->entityType)),
                    'totalCount' => Type::int(),
                    'pageInfo' => Type::nonNull(new PageInfo()),
                ],
            ]);
        }
    }

Metadata System
===============

MetadataFactory
---------------

Located at ``src/Metadata/MetadataFactory.php``:

.. code-block:: php

    class MetadataFactory
    {
        public function __construct(
            private Metadata $metadata,
            private EntityManager $entityManager,
            private Config $config,
            private GlobalEnable $globalEnable,
            private EventDispatcher $eventDispatcher,
        ) {}

        public function getMetadata(): Metadata
        {
            // Get all entity class names from Doctrine
            $entityClasses = $this->entityManager
                ->getMetadataFactory()
                ->getAllMetadata();

            foreach ($entityClasses as $classMetadata) {
                $this->processEntity($classMetadata);
            }

            // Fire metadata event for customization
            $event = new MetadataEvent('metadata', $this->metadata);
            $this->eventDispatcher->dispatch($event);

            return $this->metadata;
        }

        private function processEntity(ClassMetadata $classMetadata): void
        {
            $entityClass = $classMetadata->getName();

            // Get Entity attributes
            $entityAttributes = $this->getEntityAttributes($entityClass);

            // Filter by configured group
            $entityAttributes = $this->filterByGroup($entityAttributes);

            if (empty($entityAttributes) && !$this->config->getGlobalEnable()) {
                return; // Entity not exposed
            }

            // Extract field metadata
            $this->processFields($entityClass, $classMetadata);

            // Extract association metadata
            $this->processAssociations($entityClass, $classMetadata);
        }
    }

**Metadata Structure:**

.. code-block:: php

    $metadata = [
        'App\\Entity\\Artist' => [
            'typeName' => 'Artist_default',
            'description' => 'Artists',
            'limit' => 1000,
            'excludeFilters' => [],
            'fields' => [
                'id' => [
                    'type' => 'integer',
                    'nullable' => false,
                    'description' => 'Primary key',
                    'excludeFilters' => [],
                    'alias' => null,
                ],
                'name' => [
                    'type' => 'string',
                    'nullable' => false,
                    'description' => 'Artist name',
                    'excludeFilters' => [Filters::EQ],
                    'alias' => null,
                ],
                'performances' => [
                    'type' => 'association',
                    'targetEntity' => 'App\\Entity\\Performance',
                    'associationType' => 'OneToMany',
                    'mappedBy' => 'artist',
                    'limit' => null,
                    'criteriaEventName' => 'Artist.performances.criteria',
                    'excludeFilters' => [],
                    'alias' => null,
                ],
            ],
        ],
    ];

GlobalEnable Feature
--------------------

Located at ``src/Metadata/GlobalEnable.php``:

When ``Config::globalEnable`` is true, all fields/associations are exposed without attributes:

.. code-block:: php

    class GlobalEnable
    {
        public function processEntity(string $entityClass): void
        {
            $classMetadata = $this->entityManager
                ->getClassMetadata($entityClass);

            // Add all fields
            foreach ($classMetadata->getFieldNames() as $fieldName) {
                if (in_array($fieldName, $this->config->getIgnoreFields())) {
                    continue;
                }

                $this->metadata[$entityClass]['fields'][$fieldName] = [
                    'type' => $classMetadata->getTypeOfField($fieldName),
                    'nullable' => $classMetadata->isNullable($fieldName),
                    // ...
                ];
            }

            // Add all associations
            foreach ($classMetadata->getAssociationNames() as $assocName) {
                if (in_array($assocName, $this->config->getIgnoreFields())) {
                    continue;
                }

                $this->metadata[$entityClass]['fields'][$assocName] = [
                    'type' => 'association',
                    'targetEntity' => $classMetadata->getAssociationTargetClass($assocName),
                    // ...
                ];
            }
        }
    }

Query Resolution
================

ResolveEntityFactory
--------------------

Creates resolve closures for entity queries:

.. code-block:: php

    class ResolveEntityFactory
    {
        public function get(Entity $entity, ?string $eventName = null): Closure
        {
            return function ($source, array $args, $context, ResolveInfo $info) use ($entity, $eventName) {
                $entityClass = $entity->getEntityClass();

                // Build QueryBuilder
                $queryBuilder = $this->entityManager
                    ->createQueryBuilder()
                    ->select('entity')
                    ->from($entityClass, 'entity');

                // Apply filters
                if (isset($args['filter'])) {
                    $queryBuilderFilter = new QueryBuilderFilter();
                    $queryBuilderFilter->apply($args['filter'], $queryBuilder, $entity);
                }

                // Decode pagination parameters
                $paginationFields = $this->paginationService
                    ->decodePaginationFields($args['pagination'] ?? []);

                // Calculate offset and limit
                $offsetAndLimit = $this->paginationService
                    ->calculateOffsetAndLimit($paginationFields, $this->config->getLimit());

                // Fire QueryBuilder event for customization
                if ($eventName) {
                    $event = new QueryBuilderEvent(
                        $eventName,
                        $queryBuilder,
                        $offsetAndLimit['offset'],
                        $offsetAndLimit['limit'],
                        $source, $args, $context, $info
                    );
                    $this->eventDispatcher->dispatch($event);
                }

                // Apply pagination
                $queryBuilder->setFirstResult($offsetAndLimit['offset']);
                $queryBuilder->setMaxResults($offsetAndLimit['limit']);

                // Execute query
                $paginator = new Paginator($queryBuilder->getQuery());
                $itemCount = $paginator->count();
                $results = $paginator->getQuery()->getResult();

                // Build response
                $edges = $this->paginationService->buildEdges($results, $offsetAndLimit['offset']);
                $cursors = $this->paginationService->buildCursors($offsetAndLimit['offset'], $itemCount, count($results));

                return $this->paginationService->buildPaginationResponse($edges, $cursors, $itemCount);
            };
        }
    }

ResolveCollectionFactory
------------------------

Creates resolve closures for association queries:

.. code-block:: php

    class ResolveCollectionFactory
    {
        public function get(Entity $entity): Closure
        {
            return function ($source, array $args, $context, ResolveInfo $info) {
                // Determine association name (may be aliased)
                $targetCollectionName = $this->resolveAssociationName($info->fieldName, $source);

                // Get association metadata
                $sourceMetadata = $this->entityManager->getClassMetadata(get_class($source));
                $association = $sourceMetadata->getAssociationMapping($targetCollectionName);

                // Build QueryBuilder based on association type
                $queryBuilder = $this->buildAssociationQueryBuilder($source, $association);

                // Apply filters at database level (not in-memory!)
                if (isset($args['filter'])) {
                    $queryBuilderFilter = new QueryBuilderFilter();
                    $queryBuilderFilter->apply($args['filter'], $queryBuilder, $targetEntity);
                }

                // Apply pagination and execute...
                // (Similar to ResolveEntityFactory)
            };
        }

        private function buildAssociationQueryBuilder($source, array $association): QueryBuilder
        {
            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder->select('entity')->from($targetClassName, 'entity');

            if (isset($association['joinTable'])) {
                // ManyToMany: JOIN through intermediate table
                $queryBuilder->innerJoin($joinTable, 'jt', 'WITH', 'jt.target_id = entity.id');
                $queryBuilder->where('jt.source_id = :sourceId');
                $queryBuilder->setParameter('sourceId', $source->getId());

            } elseif (isset($association['mappedBy'])) {
                // OneToMany: Filter by foreign key
                $queryBuilder->where('entity.' . $association['mappedBy'] . ' = :source');
                $queryBuilder->setParameter('source', $source);

            } elseif (isset($association['inversedBy'])) {
                // ManyToOne from owning side (uncommon for collections)
                $queryBuilder->innerJoin($entityClassName, 'source', 'WITH', 'source.' . $associationName . ' = entity');
                $queryBuilder->where('source = :source');
                $queryBuilder->setParameter('source', $source);
            }

            return $queryBuilder;
        }
    }

**Key Optimization:**

Collections use QueryBuilder for database-level filtering, not Doctrine Collections + Criteria for in-memory filtering. This provides:

- 83% faster query execution
- 90% memory reduction
- Proper database index utilization
- No triple iteration (was filtering same collection 3x)

Pagination Service
==================

Located at ``src/Pagination/PaginationService.php``:

.. code-block:: php

    class PaginationService
    {
        public function decodePaginationFields(array $pagination): array
        {
            // Decode base64 cursors
            $fields = ['first' => 0, 'last' => 0, 'before' => 0, 'after' => 0];

            foreach ($pagination as $field => $value) {
                $fields[$field] = $value;

                if ($field === 'after') {
                    // cursor: base64(offset) -> offset+1
                    $fields[$field] = (int) base64_decode($value) + 1;
                }

                if ($field === 'before') {
                    // cursor: base64(offset) -> offset
                    $fields[$field] = (int) base64_decode($value);
                }
            }

            return $fields;
        }

        public function calculateOffsetAndLimit(array $fields, int $defaultLimit, ?int $itemCount = null): array
        {
            $offset = 0;
            $limit = $defaultLimit;

            // Handle 'first' and 'after' (forward pagination)
            if ($fields['first']) {
                $limit = min($fields['first'], $defaultLimit);
                $offset = $fields['after'] ?: 0;
            }

            // Handle 'last' (backward pagination)
            if ($fields['last']) {
                $limit = min($fields['last'], $defaultLimit);

                if ($fields['before']) {
                    $offset = max(0, $fields['before'] - $limit);
                }
                // If no 'before', offset calculated after count query
            }

            // Prevent negative offsets
            $offset = max(0, $offset);

            return ['offset' => $offset, 'limit' => $limit];
        }

        public function buildEdges(iterable $items, int $offset): array
        {
            $edges = [];
            $index = $offset;

            foreach ($items as $item) {
                $edges[] = [
                    'cursor' => base64_encode((string) $index),
                    'node' => $item,
                ];
                $index++;
            }

            return $edges;
        }

        public function buildCursors(int $offset, int $totalCount, int $resultCount): array
        {
            $endOffset = $offset + $resultCount - 1;

            return [
                'startCursor' => $resultCount > 0 ? base64_encode((string) $offset) : null,
                'endCursor' => $resultCount > 0 ? base64_encode((string) $endOffset) : null,
                'hasNextPage' => ($offset + $resultCount) < $totalCount,
                'hasPreviousPage' => $offset > 0,
            ];
        }

        public function buildPaginationResponse(array $edges, array $cursors, int $totalCount): array
        {
            return [
                'edges' => $edges,
                'totalCount' => $totalCount,
                'pageInfo' => [
                    'startCursor' => $cursors['startCursor'],
                    'endCursor' => $cursors['endCursor'],
                    'hasNextPage' => $cursors['hasNextPage'],
                    'hasPreviousPage' => $cursors['hasPreviousPage'],
                ],
            ];
        }
    }

**Design Rationale:**

Originally, pagination logic was duplicated across ResolveEntityFactory (~125 lines) and ResolveCollectionFactory (~125 lines) with 85% identical code. Extracting to PaginationService:

- Eliminated ~120 lines of duplicate code
- Single source of truth for pagination
- Easier to test in isolation
- Consistent pagination behavior

Hydration System
================

HydratorContainer
-----------------

Located at ``src/Hydrator/HydratorContainer.php``:

.. code-block:: php

    class HydratorContainer
    {
        private array $hydrators = [];

        public function get(string $entityClass): DoctrineHydrator
        {
            if (!isset($this->hydrators[$entityClass])) {
                $this->hydrators[$entityClass] = $this->build($entityClass);
            }
            return $this->hydrators[$entityClass];
        }

        private function build(string $entityClass): DoctrineHydrator
        {
            $entity = $this->entityTypeContainer->get($entityClass);
            $hydrator = new DoctrineHydrator($this->entityManager);

            // Configure extraction strategies
            foreach ($entity->getFields() as $fieldName => $fieldData) {
                $strategy = $this->getStrategy($fieldName, $fieldData);
                if ($strategy) {
                    $hydrator->addStrategy($fieldName, $strategy);
                }
            }

            // Set extraction map for aliased fields
            if ($entity->getExtractionMap()) {
                $hydrator->setNamingStrategy(new MapNamingStrategy($entity->getExtractionMap()));
            }

            return $hydrator;
        }
    }

Extraction Strategies
---------------------

Located at ``src/Hydrator/Strategy/``:

.. code-block:: php

    // FieldDefault: Handles basic field extraction
    class FieldDefault implements StrategyInterface
    {
        public function extract($value, ?object $object = null): mixed
        {
            return $value;
        }
    }

    // ToInteger: Convert to int
    class ToInteger implements StrategyInterface
    {
        public function extract($value, ?object $object = null): ?int
        {
            return $value !== null ? (int) $value : null;
        }
    }

    // ToBoolean: Convert to bool
    class ToBoolean implements StrategyInterface
    {
        public function extract($value, ?object $object = null): ?bool
        {
            return $value !== null ? (bool) $value : null;
        }
    }

    // Collection: Handle Doctrine collections
    class Collection implements StrategyInterface
    {
        public function extract($value, ?object $object = null): ?array
        {
            if ($value instanceof PersistentCollection) {
                return $value->toArray();
            }
            return $value;
        }
    }

Filter System
=============

FilterFactory
-------------

Located at ``src/Filter/FilterFactory.php``:

.. code-block:: php

    class FilterFactory
    {
        public function get(Entity $entity): InputObjectType
        {
            $entityClass = $entity->getEntityClass();
            $fields = [];

            // Add field filters
            foreach ($entity->getFields() as $fieldName => $fieldData) {
                if ($fieldData['type'] === 'association') {
                    continue;
                }

                $fields[$fieldName] = new InputObjectType\Field(
                    $fieldName,
                    $fieldData,
                    $this->typeContainer,
                    $this->config,
                );
            }

            // Add association filters
            foreach ($entity->getAssociations() as $assocName => $assocData) {
                $targetEntity = $this->entityTypeContainer->get($assocData['targetEntity']);

                $fields[$assocName] = new InputObjectType\Association(
                    $assocName,
                    $assocData,
                    $targetEntity,
                    $this,
                );
            }

            return new InputObjectType([
                'name' => 'Filter_' . $entity->getTypeName(),
                'fields' => $fields,
            ]);
        }
    }

QueryBuilder Filter Application
-------------------------------

Located at ``src/Filter/QueryBuilder.php``:

.. code-block:: php

    class QueryBuilder
    {
        public function apply(array $filters, DoctrineQueryBuilder $queryBuilder, Entity $entity): void
        {
            foreach ($filters as $fieldName => $filterValues) {
                foreach ($filterValues as $filterType => $value) {
                    $this->applyFilter($fieldName, $filterType, $value, $queryBuilder);
                }
            }
        }

        private function applyFilter(string $field, string $filterType, mixed $value, DoctrineQueryBuilder $qb): void
        {
            $paramName = $field . '_' . $filterType;

            match ($filterType) {
                'eq' => $qb->andWhere("entity.{$field} = :{$paramName}"),
                'neq' => $qb->andWhere("entity.{$field} != :{$paramName}"),
                'lt' => $qb->andWhere("entity.{$field} < :{$paramName}"),
                'lte' => $qb->andWhere("entity.{$field} <= :{$paramName}"),
                'gt' => $qb->andWhere("entity.{$field} > :{$paramName}"),
                'gte' => $qb->andWhere("entity.{$field} >= :{$paramName}"),
                'contains' => $qb->andWhere("entity.{$field} LIKE :{$paramName}"),
                'startswith' => $qb->andWhere("entity.{$field} LIKE :{$paramName}"),
                'endswith' => $qb->andWhere("entity.{$field} LIKE :{$paramName}"),
                'in' => $qb->andWhere("entity.{$field} IN (:{$paramName})"),
                'notin' => $qb->andWhere("entity.{$field} NOT IN (:{$paramName})"),
                'isnull' => $value
                    ? $qb->andWhere("entity.{$field} IS NULL")
                    : $qb->andWhere("entity.{$field} IS NOT NULL"),
                'between' => $qb->andWhere("entity.{$field} BETWEEN :{$paramName}_from AND :{$paramName}_to"),
                'sort' => $qb->addOrderBy("entity.{$field}", $value),
                default => throw new Exception("Unknown filter: {$filterType}"),
            };

            // Set parameters
            if (!in_array($filterType, ['isnull', 'sort'])) {
                $qb->setParameter($paramName, $this->prepareValue($filterType, $value));
            }
        }
    }

Event System
============

Event Dispatcher
----------------

Uses league/event v3.0 (PSR-14 compliant):

.. code-block:: php

    use League\Event\EventDispatcher;

    $dispatcher = new EventDispatcher();

    // Subscribe to events
    $dispatcher->subscribeTo(
        'Artist.queryBuilder',
        function (QueryBuilderEvent $event) {
            $event->getQueryBuilder()->andWhere('entity.active = true');
        }
    );

    // Dispatch events
    $event = new QueryBuilderEvent('Artist.queryBuilder', $queryBuilder, ...);
    $dispatcher->dispatch($event);

Available Events
----------------

**EntityDefinition Event**
  Fired when entity GraphQL type is created. Allows modification of type definition.

**QueryBuilder Event**
  Fired when QueryBuilder is created for queries. Allows custom WHERE clauses, JOINs, etc.

**Metadata Event**
  Fired when metadata is built. Allows runtime metadata modification.

Design Patterns Summary
=======================

1. **Dependency Injection**: All services registered in container
2. **Lazy Initialization**: PHP 8.4 Lazy Ghosts for deferred loading
3. **Factory Pattern**: Factories for types, resolvers, filters
4. **Strategy Pattern**: Hydration strategies for data extraction
5. **Event-Driven**: PSR-14 events for extensibility
6. **Immutable Configuration**: Config objects are readonly
7. **Type Registry**: Types registered by lowercase ID
8. **Buildable Types**: Types that depend on other types

Performance Considerations
==========================

Memory Optimization
-------------------

1. **Lazy Ghost Objects**: Entity types not initialized unless accessed
2. **Request-Scoped Hydrator Caching**: Optional caching of extraction results
3. **QueryBuilder Collections**: No in-memory collection loading

Query Optimization
------------------

1. **Database-Level Filtering**: Filters applied via QueryBuilder WHERE clauses
2. **Single Query Execution**: No triple iteration of collections
3. **Proper Index Utilization**: Database indexes used for filters
4. **Efficient Pagination**: Doctrine Paginator for count queries

Thread Safety
=============

The library is designed for PHP's shared-nothing architecture:

- **No Shared State**: Each request gets a fresh Driver instance
- **Stateless Services**: All services are stateless or request-scoped
- **Thread-Safe**: No global mutable state

However, note:

- EntityManager should be request-scoped (default in most frameworks)
- Hydrator caching is request-scoped only
- Event listeners should be stateless

Extensibility Points
====================

The library provides multiple extension points:

1. **Custom Types**: Register in TypeContainer
2. **Custom Filters**: Extend Filters enum (future: custom filter registry)
3. **Custom Hydration Strategies**: Implement StrategyInterface
4. **Event Listeners**: Subscribe to PSR-14 events
5. **Custom Resolvers**: Override resolve closures
6. **Metadata Manipulation**: Modify via Metadata event

See :doc:`advanced-topics` for detailed examples.
