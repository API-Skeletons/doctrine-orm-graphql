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
use Throwable;

use function count;
use function print_r;

/**
 * This test uses aliases for fields and associations
 */
class ExtractionMapTest extends AbstractTest
{
    public function testExtractionMap(): void
    {
        $config = new Config(['group' => 'ExtractionMap']);

        $driver = new Driver($this->getEntityManager(), $config);

        $artistEntityType = $driver->get(EntityTypeContainer::class)->get(Artist::class);

        $this->assertIsArray($artistEntityType->getExtractionMap());

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

        // This query tests aliases and filter names
        $query = '
          {
            artist (filter: {title: {eq: "Grateful Dead"}}) {
              edges {
                node {
                  title
                  gigs (filter: {key: {eq: 3}}) {
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
        $this->assertEquals(1, count($output['data']['artist']['edges'][0]['node']['gigs']['edges']));
    }

    public function testDuplicateAliasOnSameEntity(): void
    {
        $this->expectException(Throwable::class);

        $config = new Config(['group' => 'ExtractionMapDuplicate']);
        $driver = new Driver($this->getEntityManager(), $config);

        $artistEntityType = $driver->get(EntityTypeContainer::class)->get(Artist::class);
        print_r($artistEntityType->getExtractionMap());
    }
}
