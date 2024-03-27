<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class EntityFilterTest extends AbstractTest
{
    /** @var Schema[] */
    private array $schemas = [];

    /** @return Schema[] */
    public function schemaProvider(): array
    {
        parent::setUp();

        $schemas = [];

        $driver    = new Driver($this->getEntityManager());
        $schemas[] = [
            new Schema([
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
            ]),
        ];

        return $schemas;
    }

    /** @dataProvider schemaProvider */
    public function testeq(Schema $schema): void
    {
        $query  = '{ performance ( filter: {id: { eq: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testneq(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { neq: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testlt(Schema $schema): void
    {
        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { lt: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testlte(Schema $schema): void
    {
        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { lte: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testgt(Schema $schema): void
    {
        $query  = '{ performance ( filter: { artist: { eq: 1 } id: { gt: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(3, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testgte(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { gte: 2 } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testisnull(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { isnull: true } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testisnotnull(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { isnull: false } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testbetween(Schema $schema): void
    {
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

    /** @dataProvider schemaProvider */
    public function testcontains(Schema $schema): void
    {
        $query  = '{ performance ( filter: { artist: { eq: 1 } venue: { contains: "ill" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(2, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function teststartswith(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { startswith: "Soldier" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(4, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testendswith(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { endswith: "University" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['performance']['edges']));
        $this->assertEquals(3, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testin(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { in: [1,2,3] } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testnotin(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { notin: [3,4] } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['performance']['edges']));
        $this->assertEquals(1, $data['performance']['edges'][0]['node']['id']);
    }

    /** @dataProvider schemaProvider */
    public function testsort(Schema $schema): void
    {
        $query  = '{ performance ( filter: {artist: { eq: 1 } id: { sort: "desc" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { sort: "asc" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(5, $data['performance']['edges'][0]['node']['id']);

        $query  = '{ performance ( filter: {artist: { eq: 1 } venue: { sort: "desc" } } ) { edges { node { id } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['performance']['edges']));
        $this->assertEquals(4, $data['performance']['edges'][0]['node']['id']);
    }
}
