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
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_436fb9911a1f07ad8eb7057c1a8e3d2b".', $error->getMessage());
        }

        $query  = '{ artists (filter: { name: { neq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_436fb9911a1f07ad8eb7057c1a8e3d2b".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { neq: "test"} } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "Filters_String_bef569e688f8bb56acb1e0e4e430b055". Did you mean "eq"?', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { contains: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "Filters_String_bef569e688f8bb56acb1e0e4e430b055". Did you mean "notin"?', $error->getMessage());
        }
    }
}
