<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

/**
 * This test uses aliases for fields and associations
 */
class AliasMapTest extends AbstractTest
{
    public function testAliasMap(): void
    {
        $this->markTestSkipped('This test is not yet implemented');

        $config = new Config(['group' => 'AliasMap']);

        $driver = new Driver($this->getEntityManager(), $config);

        $artistEntityType = $driver->get(EntityTypeContainer::class)->get(Artist::class);

        $this->assertIsArray($artistEntityType->getAliasMap());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '
          {
            artist {
              edges {
                node {
                  title
                  gigs {
                    edges {
                      node {
                        key
                        date
                      }
                    }
                  }
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEquals(1, count($output['data']['artist']['edges']));
    }
}
