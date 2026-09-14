<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\PageInfo;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class PageInfoTest extends TestCase
{
    public function testPageInfo(): void
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
                    'performances' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $query  = '{
            performances (pagination: { first: 2 }) {
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

        $this->assertTrue($data['performances']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['performances']['pageInfo']['hasPreviousPage']);
    }

    public function testPageInfoHasPreviousPage(): void
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

    /**
     * The cursors are null when a page is empty so they must be nullable
     */
    public function testCursorsAreNullable(): void
    {
        $fields = (new PageInfo())->getFields();

        $this->assertNotInstanceOf(NonNull::class, $fields['startCursor']->getType());
        $this->assertNotInstanceOf(NonNull::class, $fields['endCursor']->getType());
        $this->assertInstanceOf(NonNull::class, $fields['hasNextPage']->getType());
        $this->assertInstanceOf(NonNull::class, $fields['hasPreviousPage']->getType());
    }

    /**
     * An empty page has no first or last node to point a cursor at
     */
    public function testPageInfoCursorsAreNullForAnEmptyPage(): void
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

        $query = '{
            performance (pagination: { before: "MA==" }) {
                pageInfo {
                    hasNextPage
                    hasPreviousPage
                    startCursor
                    endCursor
                }
                edges {
                    node {
                        id
                    }
                }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query)->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $data = $result['data'];

        $this->assertEquals([], $data['performance']['edges']);
        $this->assertNull($data['performance']['pageInfo']['startCursor']);
        $this->assertNull($data['performance']['pageInfo']['endCursor']);
        $this->assertTrue($data['performance']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['performance']['pageInfo']['hasPreviousPage']);
    }
}
