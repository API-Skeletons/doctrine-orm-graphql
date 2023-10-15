<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class PageInfoTest extends AbstractTest
{
    public function testPageInfo(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => [
                        'type' => $driver->connection($driver->type(Performance::class)),
                        'args' => [
                            'filter' => $driver->filter(Performance::class),
                            'pagination' => $driver->pagination(),
                        ],
                        'resolve' => $driver->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{
            performance {
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                }
                edges {
                    cursor
                    node {
                        id
                    }
                }
            }
        }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertFalse($data['performance']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['performance']['pageInfo']['hasPreviousPage']);
        $this->assertEquals(
            $data['performance']['edges'][0]['cursor'],
            $data['performance']['pageInfo']['startCursor'],
        );
        $this->assertEquals(
            $data['performance']['edges'][count($data['performance']['edges']) - 1]['cursor'],
            $data['performance']['pageInfo']['endCursor'],
        );
    }

    public function testPageInfoHasNextPage(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => [
                        'type' => $driver->connection($driver->type(Performance::class)),
                        'args' => [
                            'filter' => $driver->filter(Performance::class),
                            'pagination' => $driver->pagination(),
                        ],
                        'resolve' => $driver->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{
            performance (pagination: { first: 2 }) {
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
                edges {
                    node {
                        id
                    }
                }
            }
        }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertTrue($data['performance']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['performance']['pageInfo']['hasPreviousPage']);
    }

    public function testPageInfoHasPreviousPage(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => [
                        'type' => $driver->connection($driver->type(Performance::class)),
                        'args' => [
                            'filter' => $driver->filter(Performance::class),
                            'pagination' => $driver->pagination(),
                        ],
                        'resolve' => $driver->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{
            performance (pagination: { last: 2}) {
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                }
                edges {
                    node {
                        id
                    }
                }
            }
        }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertFalse($data['performance']['pageInfo']['hasNextPage']);
        $this->assertTrue($data['performance']['pageInfo']['hasPreviousPage']);
    }
}
