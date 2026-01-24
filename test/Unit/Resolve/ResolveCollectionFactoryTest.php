<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function count;
use function json_encode;

class ResolveCollectionFactoryTest extends TestCase
{
    public function testGetReturnsClosureForOneToManyAssociation(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          {
            artist {
              edges {
                node {
                  id
                  name
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
        );

        $data = $result->toArray()['data'];

        $this->assertArrayHasKey('artist', $data);
        $this->assertIsArray($data['artist']['edges']);
        $this->assertGreaterThan(0, count($data['artist']['edges']));

        // Check that performances are properly loaded
        $firstArtist = $data['artist']['edges'][0]['node'];
        $this->assertArrayHasKey('performances', $firstArtist);
        $this->assertArrayHasKey('edges', $firstArtist['performances']);
        $this->assertArrayHasKey('totalCount', $firstArtist['performances']);
    }

    public function testOneToManyWithFilters(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  name
                  performances(filter: { venue: { contains: "Center" } }) {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        $this->assertArrayHasKey('artist', $data);
        $this->assertEquals(1, count($data['artist']['edges']));

        // All performances should contain "Center"
        $performances = $data['artist']['edges'][0]['node']['performances']['edges'];
        foreach ($performances as $performance) {
            $this->assertStringContainsString('Center', $performance['node']['venue']);
        }
    }

    public function testOneToManyWithPagination(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { first: 2 }) {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        $this->assertArrayHasKey('artist', $data);
        $performances = $data['artist']['edges'][0]['node']['performances'];

        // Should only return 2 performances
        $this->assertEquals(2, count($performances['edges']));

        // Should have more pages
        $this->assertTrue($performances['pageInfo']['hasNextPage']);
        $this->assertFalse($performances['pageInfo']['hasPreviousPage']);
    }

    public function testOneToManyWithLastPagination(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { last: 2 }) {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        $this->assertArrayHasKey('artist', $data);
        $performances = $data['artist']['edges'][0]['node']['performances'];

        // Should only return 2 performances (the last 2)
        $this->assertEquals(2, count($performances['edges']));

        // Should have previous pages but no next pages
        $this->assertFalse($performances['pageInfo']['hasNextPage']);
        $this->assertTrue($performances['pageInfo']['hasPreviousPage']);
    }

    public function testOneToManyWithAfterPagination(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        // First, get the first page
        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { first: 1 }) {
                    edges {
                      cursor
                      node {
                        venue
                      }
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data   = $result->toArray()['data'];
        $cursor = $data['artist']['edges'][0]['node']['performances']['edges'][0]['cursor'];

        // Now get the next page using the cursor
        $query2 = '
          query ($id: String!, $cursor: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { first: 1, after: $cursor }) {
                    edges {
                      node {
                        venue
                      }
                    }
                    pageInfo {
                      hasPreviousPage
                    }
                  }
                }
              }
            }
          }';

        $result2 = GraphQL::executeQuery(
            schema: $schema,
            source: $query2,
            variableValues: ['id' => '1', 'cursor' => $cursor],
        );

        $data2        = $result2->toArray()['data'];
        $performances = $data2['artist']['edges'][0]['node']['performances'];

        // Should have previous pages since we used 'after'
        $this->assertTrue($performances['pageInfo']['hasPreviousPage']);
    }

    public function testOneToManyWithBeforePagination(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        // Get an item from the middle
        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { first: 3 }) {
                    edges {
                      cursor
                      node {
                        venue
                      }
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data   = $result->toArray()['data'];
        $cursor = $data['artist']['edges'][0]['node']['performances']['edges'][2]['cursor']; // Get 3rd item's cursor

        // Now get items before this cursor
        $query2 = '
          query ($id: String!, $cursor: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(pagination: { first: 1, before: $cursor }) {
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

        $result2 = GraphQL::executeQuery(
            schema: $schema,
            source: $query2,
            variableValues: ['id' => '1', 'cursor' => $cursor],
        );

        $data2 = $result2->toArray()['data'];

        // Should successfully retrieve items before the cursor
        $this->assertGreaterThanOrEqual(1, count($data2['artist']['edges'][0]['node']['performances']['edges']));
    }

    public function testWithAssociationLimit(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'AttributeLimit']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        // The AttributeLimit group has a limit of 3 for performances association
        $performances = $data['artist']['edges'][0]['node']['performances'];
        $this->assertLessThanOrEqual(3, count($performances['edges']));
    }

    public function testWithExtractionMapAlias(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ExtractionMap', 'limit' => 1]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        // Query without filter since ExtractionMap group doesn't expose id field
        // Use 'key' alias for Performance id field in ExtractionMap group
        $query = '
          {
            artist {
              edges {
                node {
                  title
                  gigs {
                    edges {
                      node {
                        key
                        date
                      }
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
        );

        $resultArray = $result->toArray();

        // Check for GraphQL errors
        if (isset($resultArray['errors'])) {
            $this->fail('GraphQL query failed: ' . json_encode($resultArray['errors']));
        }

        $data = $resultArray['data'];

        // Should be able to query using the alias 'gigs' instead of 'performances'
        $this->assertIsArray($data);
        $this->assertArrayHasKey('artist', $data);
        $this->assertGreaterThan(0, count($data['artist']['edges']));

        // Verify the aliases work
        $firstArtist = $data['artist']['edges'][0]['node'];
        $this->assertArrayHasKey('gigs', $firstArtist, 'Should have "gigs" alias for performances association');
        $this->assertArrayHasKey('title', $firstArtist, 'Should have "title" alias for name field');

        // Verify gigs is populated
        $this->assertIsArray($firstArtist['gigs']['edges']);
    }

    public function testWithEventDispatcher(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent']));

        $eventDispatched = false;

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.performances.criteria',
            function (QueryBuilderEvent $event) use (&$eventDispatched): void {
                $eventDispatched = true;

                // Verify event has expected properties
                $this->assertInstanceOf(QueryBuilder::class, $event->getQueryBuilder());
                $this->assertIsInt($event->getOffset());
                $this->assertIsInt($event->getLimit());
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
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

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        $this->assertArrayHasKey('artist', $data);
        $this->assertTrue($eventDispatched, 'Event should have been dispatched');
    }

    public function testWithConfigLimit(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default', 'limit' => 2]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
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

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        // Config limit of 2 should be applied
        $performances = $data['artist']['edges'][0]['node']['performances'];
        $this->assertLessThanOrEqual(2, count($performances['edges']));
    }

    public function testFactoryCanBeInstantiated(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $factory = $driver->get(ResolveCollectionFactory::class);

        $this->assertInstanceOf(ResolveCollectionFactory::class, $factory);
    }

    /**
     * Phase 1: Test null eventName path (line 152)
     * When eventName is not set, the event dispatcher should not be called
     */
    public function testWithoutEventName(): void
    {
        // Use 'default' group which doesn't have custom eventName on associations
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        // Verify collection is returned successfully even without eventName
        $this->assertArrayHasKey('artist', $data);
        $this->assertGreaterThan(0, count($data['artist']['edges']));
        $performances = $data['artist']['edges'][0]['node']['performances'];
        $this->assertArrayHasKey('edges', $performances);
        $this->assertGreaterThan(0, count($performances['edges']));
    }

    /**
     * Phase 2: Test query result cache (lines 187-193)
     * Test both cache miss and cache hit paths
     */
    public function testWithQueryResultCache(): void
    {
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'default', 'useQueryResultCache' => true]),
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        // First execution - cache miss (line 191-192)
        $result1 = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data1 = $result1->toArray()['data'];

        // Second execution - cache hit (line 189)
        $result2 = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data2 = $result2->toArray()['data'];

        // Results should be identical
        $this->assertEquals($data1, $data2);
        $this->assertArrayHasKey('artist', $data2);
        $this->assertGreaterThan(0, count($data2['artist']['edges']));
    }

    /**
     * Phase 2: Verify cache keys are unique per query
     */
    public function testQueryResultCacheWithDifferentQueries(): void
    {
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'default', 'useQueryResultCache' => true]),
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($id: String!, $venueFilter: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances(filter: { venue: { contains: $venueFilter } }) {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        // Query with first filter - should match "Delta Center"
        $result1 = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1', 'venueFilter' => 'Delta'],
        );

        $data1         = $result1->toArray()['data'];
        $performances1 = $data1['artist']['edges'][0]['node']['performances']['edges'];

        // Query with different filter - should match "Soldier Field"
        $result2 = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1', 'venueFilter' => 'Soldier'],
        );

        $data2         = $result2->toArray()['data'];
        $performances2 = $data2['artist']['edges'][0]['node']['performances']['edges'];

        // Different filters should yield different results
        $this->assertGreaterThan(0, count($performances1), 'Should find Delta Center');
        $this->assertGreaterThan(0, count($performances2), 'Should find Soldier Field');

        // Verify the venues are actually different
        $venue1 = $performances1[0]['node']['venue'];
        $venue2 = $performances2[0]['node']['venue'];
        $this->assertNotEquals($venue1, $venue2, 'Cache keys should be unique per query');
        $this->assertStringContainsString('Delta', $venue1);
        $this->assertStringContainsString('Soldier', $venue2);
    }

    /**
     * Phase 4: Test zero offset edge case (lines 164-166)
     * When no pagination args are provided, offset should be 0
     */
    public function testWithZeroOffset(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        // Query without pagination args - offset should be 0
        $query = '
          query ($id: String!) {
            artist(filter: { id: { eq: $id } }) {
              edges {
                node {
                  id
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
        );

        $data = $result->toArray()['data'];

        // Verify results are still returned correctly with zero offset
        $this->assertArrayHasKey('artist', $data);
        $performances = $data['artist']['edges'][0]['node']['performances'];
        $this->assertArrayHasKey('edges', $performances);
        $this->assertGreaterThan(0, count($performances['edges']));
        $this->assertGreaterThan(0, $performances['totalCount']);
    }

    /**
     * Phase 5: Test empty collection edge case
     * Query an artist with no performances
     */
    public function testEmptyCollection(): void
    {
        // Create an artist without performances
        $artist = new Artist();
        $artist->setName('Test Artist Without Performances');
        $this->getEntityManager()->persist($artist);
        $this->getEntityManager()->flush();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          query ($name: String!) {
            artist(filter: { name: { eq: $name } }) {
              edges {
                node {
                  id
                  name
                  performances {
                    edges {
                      node {
                        venue
                      }
                    }
                    totalCount
                    pageInfo {
                      hasNextPage
                      hasPreviousPage
                    }
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['name' => 'Test Artist Without Performances'],
        );

        $data = $result->toArray()['data'];

        // Verify empty collection returns proper structure
        $this->assertArrayHasKey('artist', $data);
        $this->assertEquals(1, count($data['artist']['edges']));

        $performances = $data['artist']['edges'][0]['node']['performances'];
        $this->assertArrayHasKey('edges', $performances);
        $this->assertEquals(0, count($performances['edges']), 'Should have zero performances');
        $this->assertEquals(0, $performances['totalCount'], 'Total count should be 0');
        $this->assertFalse($performances['pageInfo']['hasNextPage']);
        $this->assertFalse($performances['pageInfo']['hasPreviousPage']);
    }

    /**
     * Phase 3: Test ManyToMany relationship (owning side with joinTable)
     * This tests line 103-107 (joinTable branch)
     * Note: Attempts to test the inversedBy branch (lines 112-117) without joinTable
     * appear to be unreachable with valid Doctrine ORM configurations, as ManyToMany
     * relationships ALWAYS have a joinTable (explicit or auto-generated).
     */
    public function testManyToManyWithJoinTable(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'default']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'user' => [
                        'type' => $driver->connection(User::class),
                        'args' => [
                            'filter' => $driver->filter(User::class),
                        ],
                        'resolve' => $driver->resolve(User::class),
                    ],
                ],
            ]),
        ]);

        // Query User.recordings (ManyToMany owning side with joinTable and inversedBy)
        $query = '
          {
            user {
              edges {
                node {
                  id
                  name
                  recordings {
                    edges {
                      node {
                        id
                        source
                      }
                    }
                    totalCount
                  }
                }
              }
            }
          }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
        );

        $data = $result->toArray()['data'];

        // Verify ManyToMany collection works correctly
        $this->assertArrayHasKey('user', $data);
        $this->assertGreaterThan(0, count($data['user']['edges']));

        // At least one user should have recordings
        $foundRecordings = false;
        foreach ($data['user']['edges'] as $edge) {
            if ($edge['node']['recordings']['totalCount'] > 0) {
                $foundRecordings = true;
                $this->assertArrayHasKey('edges', $edge['node']['recordings']);
                $this->assertGreaterThan(0, count($edge['node']['recordings']['edges']));
                break;
            }
        }

        $this->assertTrue($foundRecordings, 'At least one user should have recordings');
    }
}
