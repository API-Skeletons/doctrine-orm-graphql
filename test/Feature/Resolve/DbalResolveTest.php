<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Closure;
use Doctrine\DBAL\Query\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function base64_encode;
use function count;

class DbalResolveTest extends TestCase
{
    private function getObjectType(): ObjectType
    {
        return new ObjectType([
            'name' => 'artistRow',
            'fields' => [
                'id' => Type::int(),
                'name' => Type::string(),
            ],
        ]);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        $tableName = $this->getEntityManager()
            ->getClassMetadata(Artist::class)
            ->getTableName();

        return $this->getEntityManager()
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'name')
            ->from($tableName)
            ->orderBy('id', 'ASC');
    }

    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artistRows' => $driver->dbalCompleteConnection(
                        $this->getObjectType(),
                        $this->getQueryBuilder(),
                    ),
                ],
            ]),
        ]);
    }

    public function testDbalConnection(): void
    {
        $driver = new Driver($this->getEntityManager());

        $connection = $driver->dbalConnection($this->getObjectType());

        $this->assertEquals('Connection_artistRow', $connection->name);

        $fields = $connection->getFields();
        $this->assertArrayHasKey('edges', $fields);
        $this->assertArrayHasKey('totalCount', $fields);
        $this->assertArrayHasKey('pageInfo', $fields);

        $node = $fields['edges']->getType()->getInnermostType();
        $this->assertEquals('Node_artistRow', $node->name);
        $this->assertArrayHasKey('node', $node->getFields());
        $this->assertArrayHasKey('cursor', $node->getFields());
    }

    public function testDbalResolveReturnsClosure(): void
    {
        $driver = new Driver($this->getEntityManager());

        $this->assertInstanceOf(Closure::class, $driver->dbalResolve($this->getQueryBuilder()));
    }

    public function testDbalCompleteConnectionDefinition(): void
    {
        $driver = new Driver($this->getEntityManager());

        $definition = $driver->dbalCompleteConnection($this->getObjectType(), $this->getQueryBuilder());

        $this->assertEquals('Connection_artistRow', $definition['type']->name);
        $this->assertArrayHasKey('pagination', $definition['args']);
        $this->assertEquals('Pagination', $definition['args']['pagination']->name);
        $this->assertInstanceOf(Closure::class, $definition['resolve']);
    }

    /**
     * dbalConnection(), pagination(), and dbalResolve() assembled by hand must
     * produce the same result as dbalCompleteConnection()
     */
    public function testDbalConnectionWithDbalResolve(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artistRows' => [
                        'type' => $driver->dbalConnection($this->getObjectType()),
                        'args' => ['pagination' => $driver->pagination()],
                        'resolve' => $driver->dbalResolve($this->getQueryBuilder()),
                    ],
                ],
            ]),
        ]);

        $query = '
          {
            artistRows (pagination: { first: 2 }) {
              totalCount
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(2, count($data['artistRows']['edges']));
        $this->assertEquals('Grateful Dead', $data['artistRows']['edges'][0]['node']['name']);
    }

    public function testDbalResolveWithoutPagination(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows {
              totalCount
              pageInfo {
                startCursor
                endCursor
                hasNextPage
                hasPreviousPage
              }
              edges {
                cursor
                node {
                  id
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(4, count($data['artistRows']['edges']));
        $this->assertEquals('Grateful Dead', $data['artistRows']['edges'][0]['node']['name']);
        $this->assertEquals(base64_encode('0'), $data['artistRows']['edges'][0]['cursor']);
        $this->assertFalse($data['artistRows']['pageInfo']['hasNextPage']);
        $this->assertFalse($data['artistRows']['pageInfo']['hasPreviousPage']);
    }

    public function testDbalResolveFirst(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows (pagination: { first: 2 }) {
              totalCount
              pageInfo {
                endCursor
                hasNextPage
              }
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(2, count($data['artistRows']['edges']));
        $this->assertEquals(base64_encode('1'), $data['artistRows']['pageInfo']['endCursor']);
        $this->assertTrue($data['artistRows']['pageInfo']['hasNextPage']);
    }

    public function testDbalResolveAfter(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows (pagination: { first: 1, after: "' . base64_encode('0') . '" }) {
              totalCount
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(1, count($data['artistRows']['edges']));
        $this->assertEquals(base64_encode('1'), $data['artistRows']['edges'][0]['cursor']);
    }

    public function testDbalResolveLast(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows (pagination: { last: 1 }) {
              totalCount
              pageInfo {
                hasPreviousPage
              }
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(1, count($data['artistRows']['edges']));
        $this->assertEquals(base64_encode('3'), $data['artistRows']['edges'][0]['cursor']);
        $this->assertTrue($data['artistRows']['pageInfo']['hasPreviousPage']);
    }

    /**
     * A last greater than the number of rows calculates a negative offset
     * which must be clamped to the first row
     */
    public function testDbalResolveLastGreaterThanTotalCount(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows (pagination: { last: 10 }) {
              totalCount
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(4, $data['artistRows']['totalCount']);
        $this->assertEquals(4, count($data['artistRows']['edges']));
        $this->assertEquals(base64_encode('0'), $data['artistRows']['edges'][0]['cursor']);
        $this->assertEquals('Grateful Dead', $data['artistRows']['edges'][0]['node']['name']);
    }

    public function testDbalResolveBefore(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $query = '
          {
            artistRows (pagination: { last: 1, before: "' . base64_encode('2') . '" }) {
              edges {
                cursor
                node {
                  name
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artistRows']['edges']));
        $this->assertEquals(base64_encode('1'), $data['artistRows']['edges'][0]['cursor']);
    }

    /**
     * The QueryBuilder is captured when the schema is built.  Resolving twice
     * must not inherit the offset and limit of the first resolution.
     */
    public function testQueryBuilderIsNotMutatedBetweenResolutions(): void
    {
        $driver       = new Driver($this->getEntityManager());
        $queryBuilder = $this->getQueryBuilder();
        $schema       = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artistRows' => $driver->dbalCompleteConnection($this->getObjectType(), $queryBuilder),
                ],
            ]),
        ]);

        $limited = GraphQL::executeQuery($schema, '{ artistRows (pagination: { first: 1 }) { edges { node { id } } } }')
            ->toArray()['data'];
        $this->assertEquals(1, count($limited['artistRows']['edges']));

        $all = GraphQL::executeQuery($schema, '{ artistRows { edges { node { id } } } }')
            ->toArray()['data'];
        $this->assertEquals(4, count($all['artistRows']['edges']));

        $this->assertEquals(0, $queryBuilder->getFirstResult());
        $this->assertNull($queryBuilder->getMaxResults());
    }
}
