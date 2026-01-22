================
Driver Reference
================

The ``Driver`` class is the primary entry point for the library. It extends ``Container`` and provides the public API for creating GraphQL schemas from Doctrine entities.

Class Overview
==============

.. code-block:: php

    namespace ApiSkeletons\Doctrine\ORM\GraphQL;

    class Driver extends Container
    {
        public function __construct(
            EntityManager $entityManager,
            ?Config $config = null,
            array $metadataArray = []
        );

        // Type methods
        public function type(string $id, ?string $eventName = null): mixed;
        public function connection(string $id, ?string $eventName = null): ObjectType;

        // Query methods
        public function resolve(string $id, ?string $eventName = null): Closure;
        public function filter(string $id): InputObjectType;
        public function pagination(): InputObjectType;

        // Convenience method
        public function completeConnection(
            string $id,
            ?string $entityDefinitionEventName = null,
            ?string $resolveEventName = null
        ): array;

        // Mutation method
        public function input(
            string $entityClass,
            array $requiredFields = [],
            array $optionalFields = []
        ): InputObjectType;

        // Container methods (inherited)
        public function get(string $id): mixed;
        public function has(string $id): bool;
        public function set(string $id, mixed $value): static;
    }

Constructor
===========

.. code-block:: php

    public function __construct(
        EntityManager $entityManager,
        ?Config $config = null,
        array $metadataArray = []
    )

Creates a new Driver instance.

Parameters
----------

**$entityManager** : ``Doctrine\ORM\EntityManager``
    The Doctrine EntityManager instance. This is used for all database operations,
    metadata retrieval, and entity management.

**$config** : ``ApiSkeletons\Doctrine\ORM\GraphQL\Config|null``
    Optional configuration object. If not provided, defaults will be used.
    See :doc:`config-reference` for available options.

**$metadataArray** : ``array``
    Optional pre-computed metadata array. Rarely used - allows bypassing attribute
    scanning for performance in specific scenarios.

Example
-------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Config;

    // Basic usage
    $driver = new Driver($entityManager);

    // With configuration
    $driver = new Driver($entityManager, new Config([
        'group' => 'api',
        'limit' => 500,
        'globalEnable' => true,
    ]));

Type Methods
============

type()
------

.. code-block:: php

    public function type(string $id, ?string $eventName = null): mixed

Returns a GraphQL type by ID. Searches EntityTypeContainer first, then TypeContainer.

Parameters
^^^^^^^^^^

**$id** : ``string``
    The type identifier. For entities, use the fully-qualified class name.
    For built-in types, use lowercase names ('datetime', 'date', 'pagination', etc.).

**$eventName** : ``string|null``
    Optional custom event name for EntityDefinition events. Only applies to entity types.
    Allows different type definitions for the same entity in different contexts.

Returns
^^^^^^^

``GraphQL\Type\Definition\Type``
    The requested GraphQL type.

Throws
^^^^^^

``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound``
    When the type doesn't exist. Includes suggestions for similar type names.

Examples
^^^^^^^^

.. code-block:: php

    // Get entity type
    $artistType = $driver->type(Artist::class);

    // Get built-in type
    $datetimeType = $driver->type('datetime');

    // Get entity type with custom event
    $artistType = $driver->type(Artist::class, 'artist.admin.definition');

    // Type not found throws helpful exception
    try {
        $driver->type('NonExistent');
    } catch (TypeNotFoundException $e) {
        // Exception includes available types and suggestions
        echo $e->getMessage();
        // "Type 'NonExistent' not found. Did you mean 'Artist'?"
    }

connection()
------------

.. code-block:: php

    public function connection(string $id, ?string $eventName = null): ObjectType

Returns a Connection-wrapped type for an entity, implementing the GraphQL
Complete Connection Model with edges, nodes, pageInfo, and totalCount.

Parameters
^^^^^^^^^^

**$id** : ``string``
    Entity class name.

**$eventName** : ``string|null``
    Optional custom event name for EntityDefinition events.

Returns
^^^^^^^

``GraphQL\Type\Definition\ObjectType``
    A Connection ObjectType with the following structure:

    .. code-block:: graphql

        type ArtistConnection {
            edges: [ArtistEdge]
            totalCount: Int
            pageInfo: PageInfo!
        }

        type ArtistEdge {
            cursor: String
            node: Artist
        }

        type PageInfo {
            startCursor: String
            endCursor: String
            hasNextPage: Boolean!
            hasPreviousPage: Boolean!
        }

Examples
^^^^^^^^

.. code-block:: php

    use GraphQL\Type\Schema;
    use GraphQL\Type\Definition\ObjectType;

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => [
                    'type' => $driver->connection(Artist::class),
                    'args' => [
                        'filter' => $driver->filter(Artist::class),
                        'pagination' => $driver->pagination(),
                    ],
                    'resolve' => $driver->resolve(Artist::class),
                ],
            ],
        ]),
    ]);

Query Methods
=============

resolve()
---------

.. code-block:: php

    public function resolve(string $id, ?string $eventName = null): Closure

Returns a resolve closure for querying entities. The closure handles:

- QueryBuilder construction
- Filter application
- Pagination
- Event dispatching
- Query execution
- Result hydration

Parameters
^^^^^^^^^^

**$id** : ``string``
    Entity class name.

**$eventName** : ``string|null``
    Optional custom event name for QueryBuilder events. Allows listeners to modify
    the query before execution.

Returns
^^^^^^^

``Closure``
    A resolve function with signature:

    .. code-block:: php

        function($source, array $args, $context, ResolveInfo $info): array

    The closure returns a connection response:

    .. code-block:: php

        [
            'edges' => [
                ['cursor' => 'base64...', 'node' => $entity],
                // ...
            ],
            'totalCount' => 123,
            'pageInfo' => [
                'startCursor' => 'base64...',
                'endCursor' => 'base64...',
                'hasNextPage' => true,
                'hasPreviousPage' => false,
            ],
        ]

Examples
^^^^^^^^

.. code-block:: php

    // Basic usage
    'resolve' => $driver->resolve(Artist::class)

    // With custom event name for QueryBuilder modification
    'resolve' => $driver->resolve(Artist::class, 'artist.admin.query')

    // Event listener example
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.admin.query',
        function (QueryBuilderEvent $event) {
            // Add custom WHERE clause
            $event->getQueryBuilder()->andWhere('entity.status = :status');
            $event->getQueryBuilder()->setParameter('status', 'active');

            // Access resolve context
            $args = $event->getArgs();
            $context = $event->getContext();
        }
    );

filter()
--------

.. code-block:: php

    public function filter(string $id): InputObjectType

Returns a filter InputObjectType for an entity. Filters are auto-generated based
on entity field types and attribute configuration.

Parameters
^^^^^^^^^^

**$id** : ``string``
    Entity class name.

Returns
^^^^^^^

``GraphQL\Type\Definition\InputObjectType``
    An InputObjectType containing filter fields for all exposed entity fields
    and associations.

Filter Structure
^^^^^^^^^^^^^^^^

.. code-block:: graphql

    input Filter_Artist {
        id: Filter_Artist_id
        name: Filter_Artist_name
        performances: Filter_Performance  # Nested association filter
    }

    input Filter_Artist_id {
        eq: Int
        neq: Int
        lt: Int
        lte: Int
        gt: Int
        gte: Int
        in: [Int]
        notin: [Int]
        isnull: Boolean
        between: Filter_Between_Int
        sort: String  # "ASC" or "DESC"
        sortPriority: Int
    }

    input Filter_Artist_name {
        eq: String
        neq: String
        contains: String
        startswith: String
        endswith: String
        in: [String]
        notin: [String]
        isnull: Boolean
        sort: String
        sortPriority: Int
    }

Examples
^^^^^^^^

.. code-block:: php

    // Add filter to query
    'args' => [
        'filter' => $driver->filter(Artist::class),
        'pagination' => $driver->pagination(),
    ],

    // GraphQL query with filters
    $query = '{
        artists(filter: {
            name: { contains: "Dead" }
            performances: {
                venue: { eq: "The Fillmore" }
                performanceDate: { gte: "2020-01-01" }
            }
        }) {
            edges {
                node {
                    name
                    performances {
                        edges {
                            node {
                                venue
                            }
                        }
                    }
                }
            }
        }
    }';

pagination()
------------

.. code-block:: php

    public function pagination(): InputObjectType

Returns the pagination InputObjectType for cursor-based pagination.

Returns
^^^^^^^

``GraphQL\Type\Definition\InputObjectType``
    Pagination input type with the following structure:

    .. code-block:: graphql

        input Pagination {
            first: Int      # Take first N items
            after: String   # Cursor to start after
            last: Int       # Take last N items
            before: String  # Cursor to end before
        }

Pagination Patterns
^^^^^^^^^^^^^^^^^^^

**Forward Pagination**:
    Use ``first`` to get the first N items:

    .. code-block:: graphql

        artists(pagination: { first: 10 })

**Forward Pagination with Cursor**:
    Use ``first`` and ``after`` to get next page:

    .. code-block:: graphql

        artists(pagination: { first: 10, after: "cursor..." })

**Backward Pagination**:
    Use ``last`` to get the last N items:

    .. code-block:: graphql

        artists(pagination: { last: 10 })

**Backward Pagination with Cursor**:
    Use ``last`` and ``before`` to get previous page:

    .. code-block:: graphql

        artists(pagination: { last: 10, before: "cursor..." })

Examples
^^^^^^^^

.. code-block:: php

    'args' => [
        'pagination' => $driver->pagination(),
    ],

    // GraphQL query
    $query = '{
        artists(pagination: { first: 20 }) {
            edges {
                cursor
                node {
                    name
                }
            }
            pageInfo {
                endCursor
                hasNextPage
            }
        }
    }';

    // Use endCursor for next page
    $nextPageQuery = '{
        artists(pagination: { first: 20, after: "' . $endCursor . '" }) {
            # ...
        }
    }';

Convenience Methods
===================

completeConnection()
--------------------

.. code-block:: php

    public function completeConnection(
        string $id,
        ?string $entityDefinitionEventName = null,
        ?string $resolveEventName = null
    ): array

Shortcut method that combines ``connection()``, ``filter()``, ``pagination()``,
and ``resolve()`` into a single array suitable for GraphQL field definition.

Parameters
^^^^^^^^^^

**$id** : ``string``
    Entity class name.

**$entityDefinitionEventName** : ``string|null``
    Optional custom event name for EntityDefinition events (affects type definition).

**$resolveEventName** : ``string|null``
    Optional custom event name for QueryBuilder events (affects query execution).

Returns
^^^^^^^

``array``
    Array with ``type``, ``args``, and ``resolve`` keys:

    .. code-block:: php

        [
            'type' => ObjectType,  // Connection type
            'args' => [
                'filter' => InputObjectType,
                'pagination' => InputObjectType,
            ],
            'resolve' => Closure,
        ]

Examples
^^^^^^^^

.. code-block:: php

    // Most concise way to define a query
    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(Artist::class),
                'performances' => $driver->completeConnection(Performance::class),
            ],
        ]),
    ]);

    // With custom event names
    'artists' => $driver->completeConnection(
        Artist::class,
        'artist.definition',    // EntityDefinition event
        'artist.query'          // QueryBuilder event
    )

Mutation Methods
================

input()
-------

.. code-block:: php

    public function input(
        string $entityClass,
        array $requiredFields = [],
        array $optionalFields = []
    ): InputObjectType

Creates an InputObjectType for mutations. Only specified fields are included,
allowing fine-grained control over which fields can be mutated.

Parameters
^^^^^^^^^^

**$entityClass** : ``string``
    Entity class name.

**$requiredFields** : ``array``
    Array of field names that must be provided (wrapped with Type::nonNull()).

**$optionalFields** : ``array``
    Array of field names that are optional.

Returns
^^^^^^^

``GraphQL\Type\Definition\InputObjectType``
    InputObjectType containing only the specified fields with appropriate
    GraphQL types.

Examples
^^^^^^^^

.. code-block:: php

    $schema = new Schema([
        'mutation' => new ObjectType([
            'name' => 'mutation',
            'fields' => [
                'artistCreate' => [
                    'type' => $driver->type(Artist::class),
                    'args' => [
                        'input' => Type::nonNull($driver->input(
                            Artist::class,
                            ['name'],           // Required
                            ['description']     // Optional
                        )),
                    ],
                    'resolve' => function ($root, $args) use ($driver) {
                        $artist = new Artist();
                        $artist->setName($args['input']['name']);

                        if (isset($args['input']['description'])) {
                            $artist->setDescription($args['input']['description']);
                        }

                        $em = $driver->get(EntityManager::class);
                        $em->persist($artist);
                        $em->flush();

                        return $artist;
                    },
                ],

                'artistUpdate' => [
                    'type' => $driver->type(Artist::class),
                    'args' => [
                        'id' => Type::nonNull(Type::id()),
                        'input' => Type::nonNull($driver->input(
                            Artist::class,
                            [],                 // No required fields
                            ['name', 'description']  // All optional
                        )),
                    ],
                    'resolve' => function ($root, $args) use ($driver) {
                        $em = $driver->get(EntityManager::class);
                        $artist = $em->getRepository(Artist::class)->find($args['id']);

                        if (isset($args['input']['name'])) {
                            $artist->setName($args['input']['name']);
                        }

                        if (isset($args['input']['description'])) {
                            $artist->setDescription($args['input']['description']);
                        }

                        $em->flush();
                        return $artist;
                    },
                ],
            ],
        ]),
    ]);

Container Methods
=================

get()
-----

.. code-block:: php

    public function get(string $id): mixed

Retrieves a service from the container. All services are registered in the
``Services`` trait constructor.

Available Services
^^^^^^^^^^^^^^^^^^

- ``Doctrine\ORM\EntityManager`` - The Doctrine EntityManager
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Config`` - Library configuration
- ``League\Event\EventDispatcher`` - PSR-14 event dispatcher
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Metadata`` - Entity metadata
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer`` - Custom types
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer`` - Entity types
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer`` - Hydrators
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveEntityFactory`` - Resolve factory
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory`` - Collection resolve factory
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory`` - Filter factory
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService`` - Pagination service
- ``ApiSkeletons\Doctrine\ORM\GraphQL\Input\InputFactory`` - Input factory

Examples
^^^^^^^^

.. code-block:: php

    // Access EntityManager
    $em = $driver->get(EntityManager::class);

    // Access event dispatcher
    $dispatcher = $driver->get(EventDispatcher::class);
    $dispatcher->subscribeTo('event.name', function($event) {
        // ...
    });

    // Access metadata
    $metadata = $driver->get(Metadata::class);
    $artistMetadata = $metadata[Artist::class];

    // Access config
    $config = $driver->get(Config::class);
    $limit = $config->getLimit();

has()
-----

.. code-block:: php

    public function has(string $id): bool

Checks if a service exists in the container.

Examples
^^^^^^^^

.. code-block:: php

    if ($driver->has(EntityManager::class)) {
        $em = $driver->get(EntityManager::class);
    }

set()
-----

.. code-block:: php

    public function set(string $id, mixed $value): static

Registers a service in the container. Useful for registering custom services
or overriding built-in services.

Parameters
^^^^^^^^^^

**$id** : ``string``
    Service identifier (case-insensitive).

**$value** : ``mixed``
    The service instance or a closure that returns the service.

Returns
^^^^^^^

``static``
    Returns $this for method chaining.

Examples
^^^^^^^^

.. code-block:: php

    // Register custom service
    $driver->set('my.service', new MyService());

    // Register with lazy loading
    $driver->set('my.service', function($container) {
        return new MyService($container->get(EntityManager::class));
    });

    // Register custom type
    $driver->get(TypeContainer::class)->set('customType', new CustomScalarType());

Common Patterns
===============

Basic Query Schema
------------------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use GraphQL\Type\Schema;
    use GraphQL\Type\Definition\ObjectType;

    $driver = new Driver($entityManager);

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(Artist::class),
                'performances' => $driver->completeConnection(Performance::class),
            ],
        ]),
    ]);

Schema with Mutations
---------------------

.. code-block:: php

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(Artist::class),
            ],
        ]),
        'mutation' => new ObjectType([
            'name' => 'mutation',
            'fields' => [
                'artistCreate' => [
                    'type' => $driver->type(Artist::class),
                    'args' => [
                        'input' => Type::nonNull($driver->input(Artist::class, ['name'])),
                    ],
                    'resolve' => function($root, $args) use ($driver) {
                        // Create artist...
                    },
                ],
            ],
        ]),
    ]);

Custom Event Handling
---------------------

.. code-block:: php

    $driver = new Driver($entityManager);

    // Add event listener
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilderEvent $event) {
            // Modify query
            $event->getQueryBuilder()->andWhere('entity.active = true');
        }
    );

    // Use custom event name in schema
    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(
                    Artist::class,
                    null,
                    'artist.query'  // QueryBuilder event name
                ),
            ],
        ]),
    ]);

Multiple Configurations
-----------------------

.. code-block:: php

    // Public API configuration
    $publicDriver = new Driver($entityManager, new Config([
        'group' => 'public',
        'limit' => 100,
    ]));

    // Admin API configuration
    $adminDriver = new Driver($entityManager, new Config([
        'group' => 'admin',
        'limit' => 1000,
        'globalEnable' => true,
    ]));

    // Separate schemas
    $publicSchema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $publicDriver->completeConnection(Artist::class),
            ],
        ]),
    ]);

    $adminSchema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $adminDriver->completeConnection(Artist::class),
            ],
        ]),
    ]);

Performance Tips
================

1. **Use completeConnection()**: It's the most efficient way to define queries
2. **Enable Hydrator Caching**: Set ``useHydratorCache: true`` for repeated entity access
3. **Set Appropriate Limits**: Configure reasonable ``limit`` values to prevent abuse
4. **Use Event Names Sparingly**: Only specify custom event names when needed
5. **Lazy Initialization**: Driver services are lazy-loaded, so creating multiple drivers is cheap

See :doc:`performance` for detailed optimization strategies.
