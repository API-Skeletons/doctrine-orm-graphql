===============
Advanced Topics
===============

This document covers advanced usage patterns, framework integration, testing strategies, and extending the library.

Multiple Schemas
================

Creating multiple GraphQL schemas from the same entities:

.. code-block:: php

    // Public API
    $publicDriver = new Driver($em, new Config([
        'group' => 'public',
        'limit' => 100,
        'groupSuffix' => '',
    ]));
    
    $publicSchema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $publicDriver->completeConnection(Artist::class),
            ],
        ]),
    ]);
    
    // Admin API
    $adminDriver = new Driver($em, new Config([
        'group' => 'admin',
        'limit' => 1000,
    ]));
    
    $adminSchema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $adminDriver->completeConnection(Artist::class),
                'users' => $adminDriver->completeConnection(User::class),
            ],
        ]),
    ]);

Framework Integration
=====================

Laravel Integration
-------------------

.. code-block:: php

    // app/GraphQL/Schema.php
    namespace App\GraphQL;
    
    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
    use Doctrine\ORM\EntityManager;
    
    class Schema
    {
        public function __construct(
            private EntityManager $em
        ) {}
        
        public function build(): \GraphQL\Type\Schema
        {
            $driver = new Driver($this->em, new Config([
                'group' => config('graphql.group', 'default'),
                'limit' => config('graphql.limit', 1000),
            ]));
            
            return new \GraphQL\Type\Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => [
                        'artists' => $driver->completeConnection(Artist::class),
                    ],
                ]),
            ]);
        }
    }

Symfony Integration
-------------------

.. code-block:: yaml

    # config/services.yaml
    services:
        ApiSkeletons\Doctrine\ORM\GraphQL\Driver:
            arguments:
                $entityManager: '@doctrine.orm.entity_manager'
                $config: '@app.graphql.config'

.. code-block:: php

    // src/GraphQL/SchemaFactory.php
    namespace App\GraphQL;
    
    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    
    class SchemaFactory
    {
        public function __construct(
            private Driver $driver
        ) {}
        
        public function create(): Schema
        {
            return new Schema([
                'query' => /* ... */,
            ]);
        }
    }

Custom Resolvers
================

Replacing Default Resolvers
----------------------------

.. code-block:: php

    use GraphQL\Type\Definition\ResolveInfo;
    
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
                    'resolve' => function($source, $args, $context, ResolveInfo $info) use ($driver) {
                        // Custom resolution logic
                        $em = $driver->get(EntityManager::class);
                        
                        // Apply custom filtering
                        $qb = $em->createQueryBuilder()
                            ->select('a')
                            ->from(Artist::class, 'a')
                            ->where('a.featured = true');
                        
                        // Use pagination service
                        $paginationService = $driver->get(PaginationService::class);
                        // ... build response
                    },
                ],
            ],
        ]),
    ]);

Custom Hydration Strategies
============================

.. code-block:: php

    use Laminas\Hydrator\Strategy\StrategyInterface;
    
    class FullNameStrategy implements StrategyInterface
    {
        public function extract($value, ?object $object = null): string
        {
            return $object->getFirstName() . ' ' . $object->getLastName();
        }
        
        public function hydrate($value, ?array $data = null): mixed
        {
            return $value;
        }
    }

Register and use:

.. code-block:: php

    #[GraphQL\Field(hydratorStrategy: FullNameStrategy::class)]
    private string $fullName;

Testing Strategies
==================

Unit Testing Schemas
---------------------

.. code-block:: php

    use PHPUnit\Framework\TestCase;
    use GraphQL\GraphQL;
    
    class GraphQLTest extends TestCase
    {
        private Driver $driver;
        private Schema $schema;
        
        protected function setUp(): void
        {
            $this->driver = new Driver($this->getEntityManager());
            $this->schema = $this->buildSchema($this->driver);
        }
        
        public function testArtistQuery(): void
        {
            $query = '{
                artists {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }';
            
            $result = GraphQL::executeQuery($this->schema, $query);
            $data = $result->toArray();
            
            $this->assertArrayNotHasKey('errors', $data);
            $this->assertArrayHasKey('data', $data);
        }
    }

Integration Testing
-------------------

.. code-block:: php

    public function testCompleteFlow(): void
    {
        // Create test data
        $artist = new Artist();
        $artist->setName('Test Artist');
        $em->persist($artist);
        $em->flush();
        
        // Query
        $query = '{
            artists(filter: { name: { eq: "Test Artist" } }) {
                edges {
                    node {
                        id
                        name
                    }
                }
            }
        }';
        
        $result = GraphQL::executeQuery($schema, $query);
        
        // Verify
        $this->assertEquals('Test Artist', 
            $result->toArray()['data']['artists']['edges'][0]['node']['name']
        );
    }

Extending the Library
=====================

Custom Filter Types
-------------------

Future enhancement - currently use events:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilder $event) {
            $args = $event->getArgs();
            
            // Custom "nearby" filter
            if (isset($args['filter']['location']['nearby'])) {
                $coords = $args['filter']['location']['nearby'];
                $event->getQueryBuilder()
                    ->andWhere('ST_Distance(entity.location, :point) < :radius')
                    ->setParameter('point', $coords['point'])
                    ->setParameter('radius', $coords['radius']);
            }
        }
    );

Custom Events
-------------

Dispatch custom events:

.. code-block:: php

    $dispatcher = $driver->get(EventDispatcher::class);
    
    // Custom event class
    class CustomEvent implements HasEventName
    {
        public function __construct(
            private string $eventName,
            private mixed $data
        ) {}
        
        public function eventName(): string
        {
            return $this->eventName;
        }
        
        public function getData(): mixed
        {
            return $this->data;
        }
    }
    
    // Dispatch
    $dispatcher->dispatch(new CustomEvent('custom.event', $data));

Performance Profiling
=====================

Query Logging
-------------

.. code-block:: php

    use Doctrine\DBAL\Logging\DebugStack;
    
    $sqlLogger = new DebugStack();
    $em->getConnection()->getConfiguration()->setSQLLogger($sqlLogger);
    
    GraphQL::executeQuery($schema, $query);
    
    foreach ($sqlLogger->queries as $query) {
        echo $query['sql'] . "\n";
        echo "Time: " . $query['executionMS'] . "ms\n";
    }

Memory Profiling
----------------

.. code-block:: php

    $memBefore = memory_get_usage();
    
    $result = GraphQL::executeQuery($schema, $query);
    
    $memAfter = memory_get_usage();
    $memPeak = memory_get_peak_usage();
    
    echo "Memory used: " . ($memAfter - $memBefore) . " bytes\n";
    echo "Peak memory: " . $memPeak . " bytes\n";

Best Practices
==============

1. **Use Events for Complex Logic**: Don't overload attributes
2. **Test Thoroughly**: Write integration tests for GraphQL queries
3. **Profile Before Optimizing**: Measure actual bottlenecks
4. **Set Reasonable Limits**: Prevent abuse with appropriate limits
5. **Document Custom Behavior**: Comment event listeners and custom resolvers
6. **Version Your Schema**: Use groups for API versioning
7. **Monitor Production**: Log slow queries and errors

For more details, see :doc:`performance` and :doc:`architecture`.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
