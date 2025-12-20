<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function count;

class EntityFilterTest extends TestCase
{
    /** @var Schema[] */
    private array $schemas = [];

    /** @return Schema[] */
    public static function schemaProvider(): array
    {
        return [
            'dynamic case' => [
                static function () {
                    $driver = new Driver(self::$entityManager);

                    return new Schema([
                        'query' => new ObjectType([
                            'name' => 'query',
                            'fields' => [
                                'performance' => [
                                    'type' => $driver->connection(Performance::class),
                                    'args' => [
                                        'filter' => $driver->filter(Performance::class),
                                        'pagination' => $driver->pagination(),
                                    ],
                                    'resolve' => $driver->resolve(Performance::class),
                                ],
                            ],
                        ]),
                    ]);
                },
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function testeq(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {id: { eq: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testneq(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { neq: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testlt(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { lt: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testlte(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { lte: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testgt(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { gt: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(3, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testgte(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { gte: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testisnull(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { isnull: true } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testisnotnull(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { isnull: false } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testbetween(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '
          query DateTimeBetweenTest ($from: DateTime!, $to: DateTime!)
          {
            performance (
              filter: {
                artist: { eq: 1 }
                performanceDate: {
                  between: {
                    from: $from
                    to: $to
                  }
                }
              }
            ) {
              edges {
                node {
                  id
                  performanceDate
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: [
                'from' => '1995-02-21T00:00:00+00:00',
                'to' => '1995-07-09T00:00:00+00:00',
            ],
            operationName: 'DateTimeBetweenTest',
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);

        $query  = '{ performance ( filter: { id: { between: { from: 2 to: 3 } } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testcontains(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: { artist: { eq: 1 } venue: { contains: "ill" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function teststartswith(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { startswith: "Soldier" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(4, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testendswith(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { endswith: "University" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(3, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testin(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { in: [1,2,3] } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testnotin(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { notin: [3,4] } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testsort(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { sort: "desc" sortPriority: 1 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { sort: "asc" sortPriority: 1 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { sort: "desc" sortPriority: 1 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(4, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testSortPriority(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query = '
          {
            performance (
              filter: {
                artist: {
                  eq: 2
                }
                venue: {
                  eq: "E Center"
                  sort: "asc"
                  sortPriority: 1
                }
                performanceDate: {
                  sort: "asc"
                  sortPriority: 2
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(8, $data['performance']['edges'][0]['node']['id']);

        $query = '
          {
            performance (
              filter: {
                artist: {
                  eq: 2
                }
                venue: {
                  eq: "E Center"
                  sort: "asc"
                  sortPriority: 1
                }
                performanceDate: {
                  sort: "desc"
                  sortPriority: 2
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(6, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testSortPriorityNoPriorityOneField(callable $dataProvider): void
    {
        $schema = $dataProvider();

        /**
         * This tests deprecation of not setting sortPriority when sort is set
         */
        $query = '
          {
            performance (
              filter: {
                artist: {
                  eq: 2
                }
                venue: {
                  sort: "asc"
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(7, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testSortPriorityNoPriorityTwoFields(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query = '
          {
            performance (
              filter: {
                artist: {
                  eq: 2
                }
                venue: {
                  eq: "E Center"
                  sort: "asc"
                }
                performanceDate: {
                  sort: "asc"
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(8, $data['performance']['edges'][0]['node']['id']);
    }

    #[DataProvider('schemaProvider')]
    public function testSortPriorityNoSort(callable $dataProvider): void
    {
        $schema = $dataProvider();

        $query = '
          {
            performance (
              filter: {
                artist: {
                  eq: 2
                }
                venue: {
                  eq: "E Center"
                  sortPriority: 1
                }
                performanceDate: {
                  sortPriority: 2
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['errors'];

        $this->assertEquals("Sort direction for field 'entity.venue' is not set but a sortPriority was. Please use the 'sort' filter to set the direction.", $data[0]['message']);
    }
}
