<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class PaginationTest extends AbstractTest
{
    public function testFirst(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performances' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performances (pagination: { first: 2 }) {
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

        $this->assertEquals($data['performances']['pageInfo']['startCursor'], $data['performances']['edges'][0]['cursor']);
        $this->assertEquals($data['performances']['pageInfo']['endCursor'], $data['performances']['edges'][1]['cursor']);

        $this->assertTrue($data['performances']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['performances']['pageInfo']['hasPreviousPage']);

        $this->assertEquals(2, count($data['performances']['edges']));
    }

    public function testFirstWithOffset(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performances' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performances (pagination: { first: 2 after: "MQ==" }) {
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

        $this->assertEquals($data['performances']['pageInfo']['startCursor'], $data['performances']['edges'][0]['cursor']);
        $this->assertEquals($data['performances']['pageInfo']['endCursor'], $data['performances']['edges'][1]['cursor']);

        $this->assertTrue($data['performances']['pageInfo']['hasNextPage']);
        $this->assertTrue($data['performances']['pageInfo']['hasPreviousPage']);

        $this->assertEquals(2, count($data['performances']['edges']));
    }

    public function testCollectionFirst(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query  = '{
            artists (pagination: { first: 1 }) {
                edges {
                    cursor
                    node {
                        id
                        performances (pagination: { first: 2 after: "MQ==" }) {
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
                    }
                }
            }
        }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(
            $data['artists']['edges'][0]['node']['performances']['pageInfo']['startCursor'],
            $data['artists']['edges'][0]['node']['performances']['edges'][0]['cursor'],
        );
        $this->assertEquals(
            $data['artists']['edges'][0]['node']['performances']['pageInfo']['endCursor'],
            $data['artists']['edges'][0]['node']['performances']['edges'][1]['cursor'],
        );

        $this->assertTrue($data['artists']['edges'][0]['node']['performances']['pageInfo']['hasNextPage']);
        $this->assertTrue($data['artists']['edges'][0]['node']['performances']['pageInfo']['hasPreviousPage']);

        $this->assertEquals(2, count($data['artists']['edges'][0]['node']['performances']['edges']));
    }

    public function testAfter(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performance (pagination: { first: 2 after: "MQ=="}) {
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

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(3, $data['performance']['edges'][0]['node']['id']);
    }

    public function testLast(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performance (pagination: { last: 2 }) {
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

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(8, $data['performance']['edges'][0]['node']['id']);
    }

    public function testBefore(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performance (pagination: { last: 2 before: "Nw=="}) {
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

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(6, $data['performance']['edges'][0]['node']['id']);
    }

    public function testNegativeOffset(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{ performance ( pagination: { first: 3, after: "LTU=" } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(9, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }
}
