<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ExcludeFiltersTest extends AbstractTest
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
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_a03586330c4e7326edac556450d913ee".', $error->getMessage());
        }

        $query  = '{ artists (filter: { name: { neq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_a03586330c4e7326edac556450d913ee".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { neq: "test"} } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_e55a7b533af3c46236f06d0fb99f08c6". Did you mean "eq"?', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { contains: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "Filters_String_e55a7b533af3c46236f06d0fb99f08c6". Did you mean "notin"?', $error->getMessage());
        }
    }
}
