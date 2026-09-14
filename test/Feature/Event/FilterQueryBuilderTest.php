<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function array_map;

class FilterQueryBuilderTest extends TestCase
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['limit' => 10]));
        $driver->get(EventDispatcher::class)->subscribeTo(
            'artist.querybuilder',
            function (QueryBuilderEvent $event): void {
                $this->assertInstanceOf(QueryBuilder::class, $event->getQueryBuilder());
                $this->assertEquals(0, $event->getRequestedOffset());
                $this->assertEquals(10, $event->getRequestedLimit());
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
                        'resolve' => $driver->resolve(Artist::class, 'artist.querybuilder'),
                    ],
                ],
            ]),
        ]);

        $query = '
          {
            artist (
              filter: {
                name: {
                  contains: "dead"
                }
              }
            ) {
              edges {
                node {
                  id
                  name
                  performances {
                    edges {
                      node {
                        venue
                        recordings {
                          edges {
                            node {
                              source
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        ';

        GraphQL::executeQuery($schema, $query);
    }

    /**
     * The event is dispatched before the rows are counted
     *
     * A listener therefore sees the window the client requested.  A backward
     * request cannot report its real offset because that offset is derived
     * from a count which has not been taken yet, so it reports zero while the
     * query itself still returns the last rows.
     */
    public function testEventReportsTheRequestedWindowForABackwardRequest(): void
    {
        $driver     = new Driver($this->getEntityManager(), new Config(['limit' => 10]));
        $dispatched = [];

        $driver->get(EventDispatcher::class)->subscribeTo(
            'artist.querybuilder',
            static function (QueryBuilderEvent $event) use (&$dispatched): void {
                $dispatched[] = [
                    'offset' => $event->getRequestedOffset(),
                    'limit'  => $event->getRequestedLimit(),
                ];
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => ['pagination' => $driver->pagination()],
                        'resolve' => $driver->resolve(Artist::class, 'artist.querybuilder'),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artist (pagination: { last: 2 }) { totalCount edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        // The listener sees the requested window, not the resolved one
        $this->assertEquals([['offset' => 0, 'limit' => 2]], $dispatched);

        // The query itself still returns the last two rows
        $totalCount = $result['data']['artist']['totalCount'];
        $ids        = array_map(
            static fn (array $edge): int => (int) $edge['node']['id'],
            $result['data']['artist']['edges'],
        );

        $this->assertEquals([$totalCount - 1, $totalCount], $ids);
    }
}
