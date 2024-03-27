<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function base64_encode;
use function count;

class CollectionFilterTest extends AbstractTest
{
    protected Schema $schema;

    public function setUp(): void
    {
        parent::setUp();

        $driver = new Driver($this->getEntityManager());

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
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
    }

    public function testEq(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {id: { eq: 2 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testNeq(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {id: { neq: 2 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testlt(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {id: { lt: 2 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testlte(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {id: { lte: 2 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testgt(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {id: { gt: 1 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testgte(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { id: { gte: 2 } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(4, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testisnull(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: {venue: { isnull: true } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(5, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testbetween(): void
    {
        $query  = '
          query BetweenTest($from: Int!, $to: Int!) {
            artist {
              edges {
                node {
                  performances ( filter: {
                    id: {
                      between: {
                        from: $from, to: $to
                      }
                    }
                  } ) {
                    edges {
                      node {
                        id
                      }
                    }
                  }
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery(
            schema: $this->schema,
            source: $query,
            variableValues: [
                'from' => 2,
                'to' => 3,
            ],
            operationName: 'BetweenTest',
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);

        $query  = '
          query BetweenDatesTest ($from: DateTime!, $to: DateTime!) {
            artist {
              edges {
                node {
                  performances (
                    filter: {
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
                      }
                    }
                  }
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery(
            schema: $this->schema,
            source: $query,
            variableValues: [
                'from' => '1995-02-21T00:00:00+00:00',
                'to' => '1995-07-09T00:00:00+00:00',
            ],
            operationName: 'BetweenDatesTest',
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testcontains(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { venue: { contains: "ill" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(2, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function teststartwith(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { venue: { startswith: "Soldier" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(4, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testendswith(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { venue: { endswith: "University" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(3, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testin(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { id: { in: [1,2,3] } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testnotin(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { id: { notin: [3,4] } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testsort(): void
    {
        $query  = '{ artist { edges { node { performances ( filter: { id: { sort: "desc" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(5, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);

        $query  = '{ artist { edges { node { performances ( filter: {  venue: { sort: "asc" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(5, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);

        $query  = '{ artist { edges { node { performances ( filter: { venue: { sort: "desc" } } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(4, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testfirst(): void
    {
        $query  = '{ artist { edges { node { performances ( pagination: { first: 2 } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testfirstafter(): void
    {
        $after  = base64_encode((string) 1);
        $query  = '{ artist { edges { node { performances ( pagination: { first: 2, after:"' . $after . '" } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(3, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testlast(): void
    {
        $query  = '{ artist { edges { node { performances ( pagination: { last: 3 } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(3, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(3, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testlastbefore(): void
    {
        $after  = base64_encode((string) 4);
        $query  = '{ artist { edges { node { performances ( pagination: { last: 2, before:"' . $after . '" } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(2, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(3, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }

    public function testNegativeOffset(): void
    {
        $query  = '{ artist { edges { node { performances ( pagination: { first: 3, after: "LTU=" } ) { edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($this->schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(5, count($data['artist']['edges'][0]['node']['performances']['edges']));
        $this->assertEquals(1, $data['artist']['edges'][0]['node']['performances']['edges'][0]['node']['id']);
    }
}
