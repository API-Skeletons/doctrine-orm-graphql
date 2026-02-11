<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function strtoupper;

class BasicComputedFieldTest extends TestCase
{
    public function testComputedFieldInMetadata(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldTest']));

        $entityType = $driver->get(EntityTypeContainer::class)->get(Artist::class);
        $metadata   = $entityType->getMetadata();

        // Verify computed field metadata structure
        $this->assertArrayHasKey('computedFields', $metadata);
        $this->assertArrayHasKey('fullName', $metadata['computedFields']);
        $this->assertEquals('getFullName', $metadata['computedFields']['fullName']['method']);
        $this->assertEquals('string', $metadata['computedFields']['fullName']['type']);
        $this->assertEquals('Full display name', $metadata['computedFields']['fullName']['description']);
    }

    public function testComputedFieldInGraphQLType(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldTest']));

        $graphQLType = $driver->type(Artist::class);
        $fields      = $graphQLType->getFields();

        // Verify computed field is in GraphQL type
        $this->assertArrayHasKey('fullName', $fields);
        $this->assertEquals('String', (string) $fields['fullName']->getType());
        $this->assertEquals('Full display name', $fields['fullName']->description);
    }

    public function testComputedFieldResolution(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldTest']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '
            {
                artists {
                    edges {
                        node {
                            id
                            name
                            fullName
                        }
                    }
                }
            }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEmpty($output['errors'] ?? []);

        $artist = $output['data']['artists']['edges'][0]['node'];
        $this->assertArrayHasKey('fullName', $artist);
        $this->assertIsString($artist['fullName']);
        // Artist getFullName() method returns name in uppercase
        $this->assertEquals(strtoupper($artist['name']), $artist['fullName']);
    }

    public function testComputedFieldWithHydratorCache(): void
    {
        // Test that computed fields work with hydrator cache enabled
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'computedFieldTest', 'useHydratorCache' => true]),
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '{ artists { edges { node { fullName } } } }';

        $result1 = GraphQL::executeQuery($schema, $query)->toArray();
        $result2 = GraphQL::executeQuery($schema, $query)->toArray();

        $this->assertEquals($result1, $result2);
        $this->assertEmpty($result1['errors'] ?? []);
        $this->assertNotEmpty($result1['data']['artists']['edges']);
    }
}
