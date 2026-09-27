<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_is_list;
use function array_map;
use function date;
use function implode;
use function is_array;

/**
 * Date and time filter values are bound as the field's Doctrine type, so they
 * match the stored value whether given as a literal or a variable
 */
class DateTimeFilterTest extends TestCase
{
    /**
     * Each filter matches the one TypeTest row
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function filterProvider(): array
    {
        $today = date('Y-m-d');

        return [
            'date eq' => [['testDate' => ['eq' => $today]]],
            'date between' => [['testDate' => ['between' => ['from' => $today, 'to' => $today]]]],
            'date_immutable eq' => [['testDateImmutable' => ['eq' => '2022-08-07']]],
            'time eq' => [['testTime' => ['eq' => '20:10:15']]],
            'time between' => [['testTime' => ['between' => ['from' => '19:00:00', 'to' => '21:00:00']]]],
            'time_immutable between' => [
                ['testTimeImmutable' => ['between' => ['from' => '19:00:00', 'to' => '21:00:00']]],
            ],
            'datetime_immutable between' => [
                [
                    'testDateTimeImmutable' => [
                        'between' => ['from' => '2022-08-07T00:00:00+00:00', 'to' => '2022-08-08T00:00:00+00:00'],
                    ],
                ],
            ],
            'date in' => [['testDate' => ['in' => [$today, '2000-01-01']]]],
        ];
    }

    /** @param array<string, mixed> $filter */
    #[DataProvider('filterProvider')]
    public function testFilterByVariable(array $filter): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = $this->getSchema($driver);

        $result = GraphQL::executeQuery(
            $schema,
            'query ($filter: ' . $driver->filter(TypeTest::class)->name . ') '
            . '{ typetest (filter: $filter) { edges { node { id } } } }',
            null,
            null,
            ['filter' => $filter],
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(1, $result['data']['typetest']['edges']);
    }

    /** @param array<string, mixed> $filter */
    #[DataProvider('filterProvider')]
    public function testFilterByLiteral(array $filter): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = $this->getSchema($driver);

        $result = GraphQL::executeQuery(
            $schema,
            '{ typetest (filter: ' . $this->toLiteral($filter) . ') { edges { node { id } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(1, $result['data']['typetest']['edges']);
    }

    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => $driver->completeConnection(TypeTest::class),
                ],
            ]),
        ]);
    }

    /** Write a filter array as a GraphQL input literal */
    private function toLiteral(mixed $value): string
    {
        if (! is_array($value)) {
            return '"' . $value . '"';
        }

        if (array_is_list($value)) {
            return '[' . implode(', ', array_map($this->toLiteral(...), $value)) . ']';
        }

        $fields = [];
        foreach ($value as $key => $item) {
            $fields[] = $key . ': ' . $this->toLiteral($item);
        }

        return '{ ' . implode(' ', $fields) . ' }';
    }
}
