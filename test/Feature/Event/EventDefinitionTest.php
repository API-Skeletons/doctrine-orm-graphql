<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use League\Event\Emitter as EventDispatcher;
use League\Event\Event;

use function str_replace;
use function trim;

class EventDefinitionTest extends AbstractTest
{
    public function testEvent(): void
    {
        $driver = new Driver($this->getEntityManager());

        $driver->get(EventDispatcher::class)->addListener(
            Artist::class . '.definition',
            static function (Event $leagueEvent, EntityDefinition $event): void {
                $definition = $event->getDefinition();

                // In order to modify the fields you must resovle the closure
                $fields = $definition['fields']();

                // Add a custom field to show the name without a prefix of 'The'
                $fields['nameUnprefix'] = [
                    'type' => Type::string(),
                    'description' => 'A computed dynamically added field',
                    'resolve' => static function ($objectValue, array $args, $context, ResolveInfo $info): mixed {
                        return trim(str_replace('The', '', $objectValue->getName()));
                    },
                ];

                $definition['fields'] = $fields;
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
          {
            artist (
              filter: {
                name: {
                  contains: "beatles"
                }
              }
            ) {
              edges {
                node {
                  id
                  name
                  nameUnprefix
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals('The Beatles', $data['artist']['edges'][0]['node']['name']);
        $this->assertEquals('Beatles', $data['artist']['edges'][0]['node']['nameUnprefix']);
    }
}
