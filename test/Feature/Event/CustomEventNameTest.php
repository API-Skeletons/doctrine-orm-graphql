<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use Doctrine\ORM\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

class CustomEventNameTest extends AbstractTest
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager());
        $driver->get(EventDispatcher::class)->subscribeTo(
            'custom.test',
            function (QueryBuilderEvent $event): void {
                $this->assertInstanceOf(QueryBuilder::class, $event->getQueryBuilder());
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection($driver->type(Artist::class)),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class, 'custom.test'),
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
}
