<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class LimitTest extends TestCase
{
    public function testEntityLimit(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'LimitTest']));

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
                    'performance' => [
                        'type' => $driver->connection(Performance::class),
                        'args' => [
                            'filter' => $driver->filter(Performance::class),
                        ],
                        'resolve' => $driver->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artist { edges { node { id name } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];
        $this->assertEquals(2, count($data['artist']['edges']));

        $query  = '{ performance { edges { node { id performanceDate } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(10, count($data['performance']['edges']));

        $query  = '{ artist { edges { node { id performances { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];
        $this->assertEquals(2, count($data['artist']['edges']));
        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(3, count($data['artist']['edges'][1]['node']['performances']['edges']));
    }

    /**
     * The config limit is the default; an entity's limit replaces it and an
     * association's limit replaces the entity's, even when larger
     */
    public function testMoreSpecificLimitReplacesConfigLimit(): void
    {
        foreach (['LimitTest' => [2, 1, null], 'AttributeLimit' => [1, 1, 3]] as $group => [$artists, $performances, $each]) {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => $group, 'limit' => 1]));
            $schema = new Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => [
                        'artist' => $driver->completeConnection(Artist::class),
                        'performance' => $driver->completeConnection(Performance::class),
                    ],
                ]),
            ]);

            // Artist has an entity limit of 2 in LimitTest; Performance has none
            $data = GraphQL::executeQuery($schema, '{ artist { edges { node { id } } } }')->toArray()['data'];
            $this->assertCount($artists, $data['artist']['edges'], $group);

            $data = GraphQL::executeQuery($schema, '{ performance { edges { node { id } } } }')->toArray()['data'];
            $this->assertCount($performances, $data['performance']['edges'], $group);

            if ($each === null) {
                continue;
            }

            // Artist.performances has an association limit of 3 in AttributeLimit
            $query = '{ artist { edges { node { performances { edges { node { id } } } } } } }';
            $data  = GraphQL::executeQuery($schema, $query)->toArray()['data'];
            $this->assertCount($each, $data['artist']['edges'][0]['node']['performances']['edges'], $group);
        }
    }
}
