<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function count;
use function uniqid;

/**
 * This test uses both EventDefinition and QueryBuidlerTest to add a new
 * field to an entity type and filter it.
 */
class EntityFilterTest extends AbstractTest
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager());

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.definition',
            static function (EntityDefinition $event): void {
                $definition = $event->getDefinition();

                // In order to modify the fields you must resovle the closure
                $fields = $definition['fields']();

                // Add a custom field to show the name without a prefix of 'The'
                $fields['performanceCount'] = [
                    'type' => Type::string(),
                    'description' => 'The number of performances for this artist',
                    'resolve' => static function ($objectValue, array $args, $context, ResolveInfo $info): mixed {
                        return count($objectValue->getPerformances());
                    },
                ];

                $definition['fields'] = $fields;
            },
        );

        $driver->get(EventDispatcher::class)->subscribeTo(
            Artist::class . '.filterQueryBuilder',
            static function (QueryBuilder $event): void {
                if (! isset($event->getArgs()['moreFilters']['performanceCount_gte'])) {
                    return;
                }

                $event->getQueryBuilder()
                    ->innerJoin('entity.performances', 'performances')
                    ->having($event->getQueryBuilder()->expr()->gte(
                        'COUNT(performances)',
                        $event->getArgs()['moreFilters']['performanceCount_gte'],
                    ))
                    ->addGroupBy('entity.id');
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                            'moreFilters' => new InputObjectType([
                                'name' => uniqid(),
                                'fields' => [
                                    'performanceCount_gte' => Type::int(),
                                ],
                            ]),
                        ],
                        'resolve' => $driver->resolve(Artist::class, Artist::class . '.filterQueryBuilder'),
                    ],
                ],
            ]),
        ]);

        $query = '
          {
            artists (
              moreFilters: {
                performanceCount_gte: 3
              }
            ) {
              edges {
                node {
                  id
                  name
                  performanceCount
                }
              }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals('Grateful Dead', $data['artists']['edges'][0]['node']['name']);
        $this->assertEquals(5, $data['artists']['edges'][0]['node']['performanceCount']);
        $this->assertEquals(1, count($data['artists']['edges']));
    }
}
