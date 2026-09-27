<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\EnumValueDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_column;
use function array_map;
use function rsort;

/**
 * The sort filter takes a SortDirection enum, so an invalid direction is
 * rejected by GraphQL validation before any resolver runs
 */
class SortDirectionTest extends TestCase
{
    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);
    }

    public function testSortIsASortDirectionEnum(): void
    {
        $driver = new Driver($this->getEntityManager());

        $sortType = $driver->filter(Performance::class)->getField('id')->getType()->getField('sort')->getType();

        $this->assertInstanceOf(EnumType::class, $sortType);
        $this->assertSame('SortDirection', $sortType->name);
        $this->assertSame(
            ['ASC', 'DESC'],
            array_map(static fn (EnumValueDefinition $value): string => $value->name, $sortType->getValues()),
        );
    }

    public function testSortByEnumValue(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $result = GraphQL::executeQuery(
            $schema,
            '{ performance (filter: { id: { sort: DESC } }) { edges { node { id } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $ids    = array_column(array_column($result['data']['performance']['edges'], 'node'), 'id');
        $sorted = $ids;
        rsort($sorted);

        $this->assertSame($sorted, $ids);
    }

    public function testSortDirectionAsVariable(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $result = GraphQL::executeQuery(
            $schema,
            'query ($direction: SortDirection) { performance (filter: { id: { sort: $direction } }) { edges { node { id } } } }',
            null,
            null,
            ['direction' => 'DESC'],
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(10, $result['data']['performance']['edges'][0]['node']['id']);
    }

    public function testInvalidSortDirectionFailsValidation(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = $this->getSchema($driver);

        $result = GraphQL::executeQuery(
            $schema,
            '{ performance (filter: { id: { sort: "garbage" } }) { edges { node { id } } } }',
        )->toArray();

        // A validation error has no data; a resolver error would
        $this->assertArrayNotHasKey('data', $result);
        $this->assertStringContainsString('SortDirection', $result['errors'][0]['message']);
    }
}
