<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ExcludeFiltersTest extends TestCase
{
    public function testExcludeCriteria(): void
    {
        $config = new Config(['group' => 'ExcludeFiltersTest']);
        $driver = new Driver($this->getEntityManager(), $config);

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
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artists (filter: { name: { eq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_d85f45158139644c1511e7f9d22d6068".', $error->getMessage());
        }

        $query  = '{ artists (filter: { name: { neq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_d85f45158139644c1511e7f9d22d6068".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { neq: "test"} } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_3d2660e0d014aec30b0fc5d8fef65535". Did you mean "eq"?', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { contains: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "Filters_String_3d2660e0d014aec30b0fc5d8fef65535". Did you mean "notin"?', $error->getMessage());
        }
    }
}
