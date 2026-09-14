<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\DBAL\Query\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_map;
use function base64_encode;

/**
 * Every permutation of the pagination arguments across all three resolvers
 *
 * The ORM and the DBAL resolvers must agree on the meaning of every
 * combination of first, last, before and after.  No argument may be silently
 * discarded and a cursor for index zero must not be read as an absent
 * argument.
 */
class PaginationPermutationTest extends TestCase
{
    private const string QUERY = 'query ($pagination: Pagination) { rows (pagination: $pagination) { '
        . 'totalCount pageInfo { hasNextPage hasPreviousPage } edges { node { id } } } }';

    private static function cursor(int $index): string
    {
        return base64_encode((string) $index);
    }

    /**
     * Ten rows, ids 1 through 10
     *
     * @return array<string, array{array<string, mixed>, int[], bool, bool}>
     */
    public static function permutationProvider(): array
    {
        return [
            // description => [pagination, expected ids, hasNextPage, hasPreviousPage]
            'no arguments'          => [[], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], false, false],
            'first'                 => [['first' => 3], [1, 2, 3], true, false],
            'first of zero'         => [['first' => 0], [], true, false],
            'last'                  => [['last' => 3], [8, 9, 10], false, true],
            'last of zero'          => [['last' => 0], [], false, true],
            'last beyond the end'   => [['last' => 1000], [1, 2, 3, 4, 5, 6, 7, 8, 9, 10], false, false],
            'after'                 => [['after' => self::cursor(2)], [4, 5, 6, 7, 8, 9, 10], false, true],
            'after the first row'   => [['after' => self::cursor(0)], [2, 3, 4, 5, 6, 7, 8, 9, 10], false, true],
            'after the last row'    => [['after' => self::cursor(9)], [], false, true],
            'after beyond the end'  => [['after' => self::cursor(50)], [], false, true],
            'before'                => [['before' => self::cursor(2)], [1, 2], true, false],
            'before the first row'  => [['before' => self::cursor(0)], [], true, false],
            'before beyond the end' => [
                ['before' => self::cursor(500)],
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                false,
                false,
            ],
            'first and after'       => [['first' => 2, 'after' => self::cursor(0)], [2, 3], true, true],
            'first and before'      => [['first' => 2, 'before' => self::cursor(3)], [1, 2], true, false],
            'last and before'       => [['last' => 2, 'before' => self::cursor(3)], [2, 3], true, true],
            'last and before the first row' => [['last' => 2, 'before' => self::cursor(0)], [], true, false],
            'last and before the second row' => [['last' => 2, 'before' => self::cursor(1)], [1], true, false],
            'last and after'        => [['last' => 2, 'after' => self::cursor(0)], [9, 10], false, true],
            'first and last'        => [['first' => 2, 'last' => 3], [1, 2], true, false],
            'after and before'      => [
                ['after' => self::cursor(0), 'before' => self::cursor(3)],
                [2, 3],
                true,
                true,
            ],
            'all four arguments'    => [
                ['first' => 2, 'last' => 3, 'after' => self::cursor(0), 'before' => self::cursor(3)],
                [2, 3],
                true,
                true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $pagination
     * @param int[]                $expectedIds
     */
    #[DataProvider('permutationProvider')]
    public function testDbalResolver(
        array $pagination,
        array $expectedIds,
        bool $hasNextPage,
        bool $hasPreviousPage,
    ): void {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'rows' => $driver->dbalCompleteConnection(
                        new ObjectType(['name' => 'performanceRow', 'fields' => ['id' => Type::int()]]),
                        $this->performanceQueryBuilder(),
                    ),
                ],
            ]),
        ]);

        $this->assertPage($schema, $pagination, $expectedIds, $hasNextPage, $hasPreviousPage);
    }

    /**
     * @param array<string, mixed> $pagination
     * @param int[]                $expectedIds
     */
    #[DataProvider('permutationProvider')]
    public function testEntityResolver(
        array $pagination,
        array $expectedIds,
        bool $hasNextPage,
        bool $hasPreviousPage,
    ): void {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['rows' => $driver->completeConnection(Performance::class)],
            ]),
        ]);

        $this->assertPage($schema, $pagination, $expectedIds, $hasNextPage, $hasPreviousPage);
    }

    /**
     * The collection resolver is exercised through an association of five rows
     *
     * The expectations are clipped to the association rather than repeated.
     */
    public function testCollectionResolver(): void
    {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artist' => $driver->completeConnection(Artist::class)],
            ]),
        ]);

        $query = 'query ($pagination: Pagination) { artist { edges { node { '
            . 'performances (pagination: $pagination) { totalCount '
            . 'pageInfo { hasNextPage hasPreviousPage } edges { node { id } } } } } } }';

        $expectations = [
            // pagination => [expected ids, hasNextPage, hasPreviousPage]
            'no arguments'        => [[], [1, 2, 3, 4, 5], false, false],
            'last beyond the end' => [['last' => 1000], [1, 2, 3, 4, 5], false, false],
            'before the first row' => [['before' => self::cursor(0)], [], true, false],
            'last and before the first row' => [['last' => 2, 'before' => self::cursor(0)], [], true, false],
            'last and after'      => [['last' => 2, 'after' => self::cursor(0)], [4, 5], false, true],
            'after and before'    => [['after' => self::cursor(0), 'before' => self::cursor(3)], [2, 3], true, true],
            'after the last row'  => [['after' => self::cursor(4)], [], false, true],
        ];

        foreach ($expectations as $description => [$pagination, $expectedIds, $hasNextPage, $hasPreviousPage]) {
            $result = GraphQL::executeQuery($schema, $query, null, null, ['pagination' => $pagination])->toArray();

            $this->assertArrayNotHasKey('errors', $result, $description);

            $connection = $result['data']['artist']['edges'][0]['node']['performances'];

            $this->assertEquals(
                $expectedIds,
                array_map(static fn (array $edge): int => $edge['node']['id'], $connection['edges']),
                $description,
            );
            $this->assertEquals(5, $connection['totalCount'], $description);
            $this->assertEquals($hasNextPage, $connection['pageInfo']['hasNextPage'], $description);
            $this->assertEquals($hasPreviousPage, $connection['pageInfo']['hasPreviousPage'], $description);
        }
    }

    /**
     * A collection which cannot match a row must not execute a query with a negative offset
     */
    public function testCollectionLastBeyondTheEndDoesNotThrow(): void
    {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artist' => $driver->completeConnection(Artist::class)],
            ]),
        ]);

        $query  = '{ artist { edges { node { performances (pagination: { last: 1000 }) '
            . '{ edges { node { id } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(5, $result['data']['artist']['edges'][0]['node']['performances']['edges']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidPaginationProvider(): array
    {
        return [
            'negative first'  => [['first' => -3], 'Pagination argument "first" must be a non-negative integer.'],
            'negative last'   => [['last' => -3], 'Pagination argument "last" must be a non-negative integer.'],
            'invalid cursor'  => [['after' => '!!!!'], 'Pagination argument "after" is not a valid cursor.'],
            'negative cursor' => [
                ['before' => base64_encode('-5')],
                'Pagination argument "before" is not a valid cursor.',
            ],
        ];
    }

    /** @param array<string, mixed> $pagination */
    #[DataProvider('invalidPaginationProvider')]
    public function testInvalidArgumentsAreReported(array $pagination, string $message): void
    {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['rows' => $driver->completeConnection(Performance::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, self::QUERY, null, null, ['pagination' => $pagination])->toArray();

        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals($message, $result['errors'][0]['message']);
    }

    private function performanceQueryBuilder(): QueryBuilder
    {
        $tableName = $this->getEntityManager()
            ->getClassMetadata(Performance::class)
            ->getTableName();

        return $this->getEntityManager()
            ->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->orderBy('id', 'ASC');
    }

    /**
     * @param array<string, mixed> $pagination
     * @param int[]                $expectedIds
     */
    private function assertPage(
        Schema $schema,
        array $pagination,
        array $expectedIds,
        bool $hasNextPage,
        bool $hasPreviousPage,
    ): void {
        $result = GraphQL::executeQuery($schema, self::QUERY, null, null, ['pagination' => $pagination])->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $connection = $result['data']['rows'];

        $this->assertEquals(
            $expectedIds,
            array_column(array_column($connection['edges'], 'node'), 'id'),
        );
        $this->assertEquals(10, $connection['totalCount']);
        $this->assertEquals($hasNextPage, $connection['pageInfo']['hasNextPage']);
        $this->assertEquals($hasPreviousPage, $connection['pageInfo']['hasPreviousPage']);
    }
}
