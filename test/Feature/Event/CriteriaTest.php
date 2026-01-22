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
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function count;

class CriteriaTest extends TestCase
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent']));

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.performances.criteria',
            function (QueryBuilderEvent $event): void {
                $this->assertInstanceOf(QueryBuilder::class, $event->getQueryBuilder());

                $event->getQueryBuilder()->andWhere('entity.venue = :venue');
                $event->getQueryBuilder()->setParameter('venue', 'Delta Center');

                $this->assertInstanceOf(Artist::class, $event->getObjectValue());
                $this->assertEquals('contextTest', $event->getContext());
                $this->assertIsArray($event->getArgs());
                $this->assertInstanceOf(ResolveInfo::class, $event->getInfo());
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
            artist (filter: { id: { eq: $id } } ) {
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
                  }
                }
              }
            }
        }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => '1'],
            contextValue:  'contextTest',
        );
        $data   = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges']));
        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(
            'Delta Center',
            $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['venue'],
        );
    }

    public function testEventFilterCollection(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'CriteriaEvent',
            'limit' => 25,
        ]));

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.performances.criteria',
            function (QueryBuilderEvent $event): void {
                $this->assertInstanceOf(QueryBuilder::class, $event->getQueryBuilder());

                $event->getQueryBuilder()->andWhere(
                    $event->getQueryBuilder()->expr()->in('entity.venue', ['Delta Center', 'Soldier Field']),
                );

                $this->assertEquals(0, $event->getOffset());
                $this->assertEquals(25, $event->getLimit());
                $this->assertInstanceOf(Artist::class, $event->getObjectValue());
                $this->assertEquals('contextTest', $event->getContext());
                $this->assertIsArray($event->getArgs());
                $this->assertInstanceOf(ResolveInfo::class, $event->getInfo());
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
            artist (filter: { id: { eq: $id } } ) {
              edges {
                node {
                  id
                  name
                  performances (filter: { venue: { sort: "DESC" } } ) {
                    edges {
                      node {
                        id
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
            contextValue:  'contextTest',
        );
        $data   = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges']));
        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(
            'Soldier Field',
            $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['venue'],
        );
        $this->assertEquals(
            'Delta Center',
            $data['artist']['edges'][0]['node']['performances']['edges'][1]['node']['venue'],
        );
    }
}
