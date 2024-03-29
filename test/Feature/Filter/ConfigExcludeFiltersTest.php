<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ConfigExcludeFiltersTest extends AbstractTest
{
    public function testConfigExcludeFilters(): void
    {
        $config = new Config([
            'excludeFilters' => [
                Filters::EQ,
                Filters::NEQ,
                Filters::CONTAINS,
            ],
        ]);

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
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_0812311810b0ba1d34247150620b78b0".', $error->getMessage());
        }

        $query  = '{ artists (filter: { name: { neq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_0812311810b0ba1d34247150620b78b0".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { neq: "test"} } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_0812311810b0ba1d34247150620b78b0".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { contains: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "Filters_String_0812311810b0ba1d34247150620b78b0". Did you mean "notin"?', $error->getMessage());
        }
    }
}
