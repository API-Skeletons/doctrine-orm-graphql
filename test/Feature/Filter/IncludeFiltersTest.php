<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class IncludeFiltersTest extends AbstractTest
{
    public function testIncludeFilters(): void
    {
        $config = new Config(['group' => 'IncludeFiltersTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performances' => [
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

        // Test entity level included filters
        $query  = '
          {
            performances (
              filter: {
                venue: {
                  contains: "Fillmore"
                }
              }
            ) {
              edges {
                node {
                  venue
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];
        $this->assertEquals('Fillmore Auditorium', $data['performances']['edges'][0]['node']['venue']);

        $query  = '
          {
            performances (
              filter: {
                venue: {
                  eq: "Fillmore Auditorium"
                }
              }
            ) {
              edges {
                node {
                  venue
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];
        $this->assertEquals('Fillmore Auditorium', $data['performances']['edges'][0]['node']['venue']);

        // Test entity level excluded filters
        $query  = '
          {
            performances (
              filter: {
                venue: {
                  in: ["Fillmore Auditorium"]
                }
              }
            ) {
              edges {
                node {
                  venue
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        foreach ($result->errors as $error) {
            $this->assertEquals('Field "in" is not defined by type "Filters_String_8254df8c7959ea47c8b536a2b975a8e6".', $error->getMessage());
        }

        // Test entity>field level included filters
        $query  = '
          {
            performances (
              filter: {
                city: {
                  eq: "Salt Lake City"
                }
              }
            ) {
              edges {
                node {
                  city
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];
        $this->assertEquals('Salt Lake City', $data['performances']['edges'][0]['node']['city']);

        // Test entity>field level excluded filters
        $query  = '
          {
            performances (
              filter: {
                city: {
                  contains: "Salt Lake City"
                }
              }
            ) {
              edges {
                node {
                  city
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        foreach ($result->errors as $error) {
            $this->assertEquals('Field "contains" is not defined by type "Filters_String_22ea8c5dceaa153b3729393465ba253d".', $error->getMessage());
        }

        // Test entity>field level included filters excluded by field level exclude
        $query  = '
          {
            performances (
              filter: {
                state: {
                  eq: "UT"
                }
              }
            ) {
              edges {
                node {
                  state
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);
        foreach ($result->errors as $error) {
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_2b95866a5016efda298ddbf2e3ed5c14". Did you mean "neq"?', $error->getMessage());
        }

        // Test entity>association level included filters
        $query  = '{
          performances (
            filter: {
              venue: { eq: "Delta Center" }
            }
          ) {
            edges {
              node {
                recordings(
                  filter: {
                    source: { contains: "DSBD" }
                  }
                ) {
                  edges {
                    node {
                      source
                    }
                  }
                }
              }
            }
          }
        }';
        $result = GraphQL::executeQuery($schema, $query);
        $data   = $result->toArray()['data'];
        $this->assertEquals(
            'DSBD > 1C > DAT; Seeded to etree by Dan Stephens',
            $data['performances']['edges'][0]['node']['recordings']['edges'][0]['node']['source'],
        );

        // Test entity>association level included filters
        $query  = '{
          performances (
            filter: {
              venue: { eq: "Delta Center" }
            }
          ) {
            edges {
              node {
                recordings(
                  filter: {
                    source: { eq: "DSBD" }
                  }
                ) {
                  edges {
                    node {
                      source
                    }
                  }
                }
              }
            }
          }
        }';
        $result = GraphQL::executeQuery($schema, $query);
        foreach ($result->errors as $error) {
            $this->assertEquals('Field "eq" is not defined by type "Filters_String_daeebc957d3b444810fef662f84b89e8".', $error->getMessage());
        }
    }
}
