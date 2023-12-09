<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class TypeNameTest extends AbstractTest
{
    public function testGroupSuffix(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'groupSuffix' => 'unittest',
            'entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\',
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

        $artistClass = $driver->get(TypeManager::class)->get(Artist::class);

        $this->assertEquals('Artist_unittest', $artistClass->getTypeName());
    }

    public function testEmptyGroupNameGlobalEnable(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'groupSuffix' => '',
            'entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\',
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

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertEquals('Artist', $driver->type(Artist::class)->name);
    }

    public function testEmptyGroupName(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'groupSuffix' => '',
            'group' => 'TypeNameTest',
            'entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\',
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

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertEquals('Artist', $driver->type(Artist::class)->name);
    }

    public function testEntityPrefix(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\',
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

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertEquals('Artist_default', $driver->type(Artist::class)->name);
    }
}
