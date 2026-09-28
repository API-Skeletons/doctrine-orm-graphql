<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

/**
 * A bigint is a String, which keeps its precision, but it has the number
 * filters and its values must be integers
 */
class BigintFilterTest extends TestCase
{
    private Driver $driver;

    private Schema $schema;

    public function setUp(): void
    {
        parent::setUp();

        $this->driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['typeTest' => $this->driver->completeConnection(TypeTest::class)],
            ]),
        ]);
    }

    /** @return mixed[] */
    private function execute(string $filter): array
    {
        return GraphQL::executeQuery(
            $this->schema,
            '{ typeTest(filter: { testBigint: { ' . $filter . ' } }) { edges { node { id testBigint } } } }',
        )->toArray();
    }

    public function testBigintHasTheNumberFilters(): void
    {
        $filter = $this->driver->filter(TypeTest::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);
        $bigint = $filter->getField('testBigint')->getType();
        $this->assertInstanceOf(InputObjectType::class, $bigint);

        $this->assertSame(
            ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'between', 'in', 'notin', 'isnull', 'sort', 'sortPriority'],
            array_keys($bigint->getFields()),
        );
    }

    /** @return array<string, array{string, int}> */
    public static function filterProvider(): array
    {
        // The value is 1234567890123
        return [
            'eq' => ['eq: "1234567890123"', 1],
            'gt' => ['gt: "1234567890122"', 1],
            'gt above' => ['gt: "1234567890123"', 0],
            'lte' => ['lte: "1234567890123"', 1],
            'between' => ['between: { from: "1000000000000", to: "2000000000000" }', 1],
            'between below' => ['between: { from: "-5", to: "1000" }', 0],
            'in' => ['in: ["1", "1234567890123"]', 1],
            'sort' => ['sort: DESC', 1],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testFilter(string $filter, int $count): void
    {
        $result = $this->execute($filter);

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount($count, $result['data']['typeTest']['edges']);
    }

    /** @return array<string, array{string, string}> */
    public static function notIntegerProvider(): array
    {
        return [
            'eq' => ['eq: "abc"', 'eq'],
            'lt' => ['lt: "1.5"', 'lt'],
            'between' => ['between: { from: "1", to: "2e3" }', 'between'],
            'in' => ['in: ["1", "two"]', 'in'],
            'notin' => ['notin: [" 1"]', 'notin'],
        ];
    }

    #[DataProvider('notIntegerProvider')]
    public function testValueMustBeAnInteger(string $filter, string $name): void
    {
        $result = $this->execute($filter);

        $this->assertSame("Filter '" . $name . "' of field 'testBigint' must be an integer.", $result['errors'][0]['message']);
    }
}
