<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

/**
 * A decimal is a String, which keeps its precision, but it has the number
 * filters and its values must be numbers
 */
class DecimalFilterTest extends TestCase
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
            '{ typeTest(filter: { testDecimal: { ' . $filter . ' } }) { edges { node { id testDecimal } } } }',
        )->toArray();
    }

    public function testDecimalIsAString(): void
    {
        $this->assertInstanceOf(StringType::class, $this->driver->type('decimal'));

        $result = $this->execute('eq: "314.15"');
        $this->assertSame('314.15', $result['data']['typeTest']['edges'][0]['node']['testDecimal']);
    }

    /**
     * A float would round a decimal of many digits
     */
    public function testPrecisionIsKept(): void
    {
        $value = '12345678901234.0123456789';

        $this->assertSame($value, $this->driver->type('decimal')->serialize((new ToString())->extract($value)));
    }

    public function testDecimalHasTheNumberFilters(): void
    {
        $filter = $this->driver->filter(TypeTest::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);
        $decimal = $filter->getField('testDecimal')->getType();
        $this->assertInstanceOf(InputObjectType::class, $decimal);

        $this->assertSame(
            ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'between', 'in', 'notin', 'isnull', 'sort', 'sortPriority'],
            array_keys($decimal->getFields()),
        );
    }

    /** @return array<string, array{string, int}> */
    public static function filterProvider(): array
    {
        // The value is 314.15
        return [
            'eq' => ['eq: "314.15"', 1],
            'gt' => ['gt: "314"', 1],
            'gt above' => ['gt: "314.15"', 0],
            'lt negative' => ['lt: "-1"', 0],
            'between' => ['between: { from: "300", to: "400.5" }', 1],
            'in' => ['in: ["1.5", "314.15"]', 1],
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
    public static function notNumberProvider(): array
    {
        return [
            'eq' => ['eq: "abc"', 'eq'],
            'exponent' => ['lt: "1e3"', 'lt'],
            'comma' => ['gt: "3,14"', 'gt'],
            'no digits after the point' => ['gt: "3."', 'gt'],
            'between' => ['between: { from: "1", to: "two" }', 'between'],
            'in' => ['in: ["1.5", ""]', 'in'],
        ];
    }

    #[DataProvider('notNumberProvider')]
    public function testValueMustBeANumber(string $filter, string $name): void
    {
        $result = $this->execute($filter);

        $this->assertSame("Filter '" . $name . "' of field 'testDecimal' must be a number.", $result['errors'][0]['message']);
    }
}
