<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\GraphQL\Feature\Criteria;

use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ConfigExcludeCriteriaTest extends AbstractTest
{
    public function testConfigExcludeCriteria(): void
    {
        $config = new Config(['excludeCriteria' => ['eq', 'neq', 'contains']]);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => [
                        'type' => $driver->connection($driver->type(Artist::class)),
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
            $this->assertEquals('Field "eq" is not defined by type "artist_default_filter_name_filters".', $error->getMessage());
        }

        $query  = '{ artists (filter: { name: { neq: "Grateful Dead" } } ) { edges { node { name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "artist_default_filter_name_filters".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { neq: "test"} } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "neq" is not defined by type "artist_default_performances_filter_venue_filters".', $error->getMessage());
        }

        $query  = '{ artists { edges { node { performances ( filter: {venue: { contains: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "artist_default_performances_filter_venue_filters". Did you mean "notin"?', $error->getMessage());
        }
    }
}
