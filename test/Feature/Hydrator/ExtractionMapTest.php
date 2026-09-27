<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

/**
 * This test uses aliases for fields and associations
 */
class ExtractionMapTest extends TestCase
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
                        band {
                          title
                        }
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

        // A to-one association is exposed under its alias and resolves
        $performanceType = $driver->type(Performance::class);
        $this->assertTrue($performanceType->hasField('band'));
        $this->assertFalse($performanceType->hasField('artist'));
        $this->assertSame(
            'Grateful Dead',
            $output['data']['artist']['edges'][0]['node']['gigs']['edges'][0]['node']['band']['title'],
        );
    }

    /**
     * The eq filter for an aliased to-one association is named by the alias
     */
    public function testToOneAssociationFilterUsesAlias(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ExtractionMap']));

        $artist = $this->getEntityManager()->getRepository(Artist::class)
            ->findOneBy(['name' => 'Grateful Dead']);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ performance (filter: { band: { eq: ' . $artist->getId() . ' } }) { totalCount edges { node { band { title } } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $expected = $this->getEntityManager()->getRepository(Performance::class)->count(['artist' => $artist]);
        $this->assertSame($expected, $result['data']['performance']['totalCount']);

        foreach ($result['data']['performance']['edges'] as $edge) {
            $this->assertSame('Grateful Dead', $edge['node']['band']['title']);
        }
    }

    public function testDuplicateAliasOnSameEntity(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Duplicate alias "duplicate"');

        $config = new Config(['group' => 'ExtractionMapDuplicate']);
        $driver = new Driver($this->getEntityManager(), $config);

        $driver->get(EntityTypeContainer::class)->get(Artist::class)->getExtractionMap();
    }

    /**
     * A duplicate alias is reported on every call, not only the first; a
     * failed build must not leave a partial map behind
     */
    public function testDuplicateAliasIsReportedOnEveryCall(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ExtractionMapDuplicate']));
        $artist = $driver->get(EntityTypeContainer::class)->get(Artist::class);

        for ($call = 1; $call <= 2; $call++) {
            try {
                $artist->getExtractionMap();
                $this->fail('Call ' . $call . ' returned an extraction map');
            } catch (MetadataException $e) {
                $this->assertStringContainsString('Duplicate alias "duplicate"', $e->getMessage());
            }
        }
    }
}
