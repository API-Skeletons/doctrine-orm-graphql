<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class LimitTest extends AbstractTest
{
    public function testEntityLimit(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'LimitTest']));

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
                    'performance' => [
                        'type' => $driver->connection($driver->type(Performance::class)),
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

        $this->assertEquals(9, count($data['performance']['edges']));

        $query  = '{ artist { edges { node { id performances { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];
        $this->assertEquals(2, count($data['artist']['edges']));
        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, count($data['artist']['edges'][1]['node']['performances']['edges']));
    }
}
