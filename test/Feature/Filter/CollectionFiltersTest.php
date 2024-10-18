<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

/**
 * Because the error
 * `Could not convert PHP value '1995-01-01 00:00:00' to type date.
 *  Expected one of the following types: null, DateTime`
 *
 * This test exists to test the filters for a collection
 */
class CollectionFiltersTest extends AbstractTest
{
    public function testLiteralFilterValues(): void
    {
        $config = new Config();

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        // Test entity level included filters
        $query  = '
          {
            artists {
              edges {
                node {
                  id
                    performances (
                      filter: {
                        performanceDate: {
                          gte: "1995-01-01T00:00:00Z"
                        }
                      }
                    ) {
                      edges {
                        node {
                          performanceDate
                        }
                      }
                    }
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];
        $this->assertEquals('1995-02-21T00:00:00+00:00', $data['artists']['edges'][0]['node']['performances']['edges'][0]['node']['performanceDate']);
    }
}
