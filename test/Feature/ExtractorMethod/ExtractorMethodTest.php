<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ExtractorMethod;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function strtoupper;

class ExtractorMethodTest extends TestCase
{
    public function testExtractorMethodInMetadata(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'extractorMethodTest']));

        $driver->type(Artist::class);

        $metadata = $driver->get(EntityTypeContainer::class)
            ->get(Artist::class)
            ->getMetadata();

        $this->assertEquals('getNameUppercased', $metadata['fields']['name']['extractorMethod']);
    }

    public function testExtractorMethodResolvesToMethodReturn(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'extractorMethodTest']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ artists { edges { node { id name } } } }')->toArray();

        $this->assertEmpty($result['errors'] ?? []);
        $this->assertNotEmpty($result['data']['artists']['edges']);

        foreach ($result['data']['artists']['edges'] as $edge) {
            // name should be the uppercased value from getNameUppercased(), not the raw stored value
            $this->assertEquals(strtoupper($edge['node']['name']), $edge['node']['name']);
        }
    }

    public function testExtractorMethodFieldNotInFilters(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'extractorMethodTest']));

        $filterType = $driver->filter(Artist::class);
        $fields     = $filterType->getFields();

        // name has extractorMethod so must not appear in filters
        $this->assertArrayNotHasKey('name', $fields);

        // id has no extractorMethod so must still be filterable
        $this->assertArrayHasKey('id', $fields);
    }
}
