<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
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
}
