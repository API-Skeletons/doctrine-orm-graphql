<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class ComputedFieldFilterTest extends TestCase
{
    public function testComputedFieldNotInFilterType(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldTest']));

        $filterType = $driver->filter(Artist::class);
        $fields     = $filterType->getFields();

        // Verify computed fields are NOT in filter InputObject
        $this->assertArrayNotHasKey('fullName', $fields);

        // Verify regular fields ARE in filter
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('id', $fields);
    }

    public function testComputedFieldQueryWithoutFilter(): void
    {
        // Verify you can query computed fields
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'computedFieldTest']));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        // This should work (querying computed field)
        $query  = '{ artists { edges { node { fullName } } } }';
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertEmpty($result['errors'] ?? []);

        if (empty($result['data']['artists']['edges'])) {
            return;
        }

        $this->assertArrayHasKey('fullName', $result['data']['artists']['edges'][0]['node']);
    }
}
