<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToBoolean;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToFloat;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToInteger;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\EnumTypes;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum\Priority;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum\Size;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Laminas\Hydrator\Strategy\StrategyInterface;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;

/**
 * A field mapped with an enumType is represented by the enum's value
 */
class EnumTypeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $em = $this->getEntityManager();
        $em->persist(new EnumTypes(Size::Small, Priority::High, Size::Large));
        $em->persist(new EnumTypes(Size::Large, Priority::Low));
        $em->flush();
        $em->clear();
    }

    /** @return mixed[] */
    private function execute(string $group, string $query): array
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['enumTypes' => $driver->completeConnection(EnumTypes::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return array_column($result['data']['enumTypes']['edges'], 'node');
    }

    /** @return array<string, array{string}> */
    public static function groupProvider(): array
    {
        return [
            'by value' => ['EnumTypes'],
            'by reference' => ['EnumTypesByReference'],
        ];
    }

    #[DataProvider('groupProvider')]
    public function testValuesAreSerialized(string $group): void
    {
        $this->assertSame(
            [
                ['size' => 'small', 'priority' => 5, 'optionalSize' => 'large'],
                ['size' => 'large', 'priority' => 1, 'optionalSize' => null],
            ],
            $this->execute($group, '{ enumTypes { edges { node { size priority optionalSize } } } }'),
        );
    }

    /** @return array<string, array{string, int[]}> */
    public static function filterProvider(): array
    {
        return [
            'eq' => ['size: { eq: "small" }', [1]],
            'in' => ['size: { in: ["small", "large"] }', [1, 2]],
            'integer' => ['priority: { gt: 1 }', [1]],
            'isnull' => ['optionalSize: { isnull: true }', [2]],
        ];
    }

    /** @param int[] $ids */
    #[DataProvider('filterProvider')]
    public function testFilters(string $filter, array $ids): void
    {
        $nodes = $this->execute('EnumTypes', '{ enumTypes(filter: { ' . $filter . ' }) { edges { node { id } } } }');

        $this->assertSame($ids, array_column($nodes, 'id'));
    }

    /**
     * Every field strategy of this library extracts an enum by its value
     *
     * @return array<string, array{StrategyInterface, mixed, mixed}>
     */
    public static function strategyProvider(): array
    {
        return [
            'FieldDefault' => [new FieldDefault(), Size::Small, 'small'],
            'ToString' => [new ToString(), Priority::High, '5'],
            'ToInteger' => [new ToInteger(), Priority::High, 5],
            'ToFloat' => [new ToFloat(), Priority::High, 5.0],
            'ToBoolean' => [new ToBoolean(), Priority::High, true],
        ];
    }

    #[DataProvider('strategyProvider')]
    public function testStrategiesExtractTheValue(StrategyInterface $strategy, mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, $strategy->extract($value));
    }
}
