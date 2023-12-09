<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class GlobalByValueTest extends AbstractTest
{
    public function testGlobalByValueGlobalEnableFalse(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'globalEnable',
            'globalByValue' => false,
            'globalEnable' => true,
        ]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection($driver->type(Artist::class)),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{
          artist {
            edges {
              node {
                performances ( filter: {venue: { neq: "test" } } ) {
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
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertFalse($driver->get(Config::class)->getGlobalByValue());
    }

    public function testGlobalByValueFalse(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['globalByValue' => false]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection($driver->type(Artist::class)),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertFalse($driver->get(Config::class)->getGlobalByValue());
    }
}
