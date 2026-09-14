<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Pagination as PaginationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;

class PaginationServiceTest extends TestCase
{
    private PaginationService $service;

    protected function setUp(): void
    {
        $this->service = new PaginationService();
    }

    public function testDecodePaginationFieldsEmpty(): void
    {
        $this->assertEquals([
            'first'  => null,
            'last'   => null,
            'before' => null,
            'after'  => null,
        ], $this->service->decodePaginationFields([]));
    }

    public function testDecodePaginationFieldsWithFirst(): void
    {
        $this->assertEquals([
            'first'  => 10,
            'last'   => null,
            'before' => null,
            'after'  => null,
        ], $this->service->decodePaginationFields(['first' => 10]));
    }

    public function testDecodePaginationFieldsWithLast(): void
    {
        $this->assertEquals([
            'first'  => null,
            'last'   => 5,
            'before' => null,
            'after'  => null,
        ], $this->service->decodePaginationFields(['last' => 5]));
    }

    public function testDecodePaginationFieldsWithAfter(): void
    {
        $this->assertEquals([
            'first'  => null,
            'last'   => null,
            'before' => null,
            'after'  => 10, // 9 + 1
        ], $this->service->decodePaginationFields(['after' => base64_encode('9')]));
    }

    public function testDecodePaginationFieldsWithBefore(): void
    {
        $this->assertEquals([
            'first'  => null,
            'last'   => null,
            'before' => 20,
            'after'  => null,
        ], $this->service->decodePaginationFields(['before' => base64_encode('20')]));
    }

    /**
     * A cursor for index zero must not be mistaken for an absent argument
     */
    public function testDecodePaginationFieldsDistinguishesIndexZeroFromAbsent(): void
    {
        $result = $this->service->decodePaginationFields([
            'before' => base64_encode('0'),
            'after'  => base64_encode('0'),
        ]);

        $this->assertSame(0, $result['before']);
        $this->assertSame(1, $result['after']);
    }

    /**
     * A zero count is a request for no rows, not an absent argument
     */
    public function testDecodePaginationFieldsKeepsZeroCounts(): void
    {
        $result = $this->service->decodePaginationFields([
            'first' => 0,
            'last'  => 0,
        ]);

        $this->assertSame(0, $result['first']);
        $this->assertSame(0, $result['last']);
    }

    public function testDecodePaginationFieldsIgnoresNulls(): void
    {
        $this->assertEquals([
            'first'  => null,
            'last'   => null,
            'before' => null,
            'after'  => null,
        ], $this->service->decodePaginationFields([
            'first'  => null,
            'last'   => null,
            'before' => null,
            'after'  => null,
        ]));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidArgumentProvider(): array
    {
        return [
            'negative first'  => [['first' => -3], 'Pagination argument "first" must be a non-negative integer.'],
            'negative last'   => [['last' => -3], 'Pagination argument "last" must be a non-negative integer.'],
            'non numeric'     => [['first' => 'abc'], 'Pagination argument "first" must be a non-negative integer.'],
            'undecodable'     => [['after' => '!!!!'], 'Pagination argument "after" is not a valid cursor.'],
            'negative cursor' => [['after' => base64_encode('-5')], 'Pagination argument "after" is not a valid cursor.'],
            'non numeric cursor' => [['before' => base64_encode('abc')], 'Pagination argument "before" is not a valid cursor.'],
            'cursor not a string' => [['before' => 5], 'Pagination argument "before" is not a valid cursor.'],
        ];
    }

    /** @param array<string, mixed> $pagination */
    #[DataProvider('invalidArgumentProvider')]
    public function testDecodePaginationFieldsRejectsInvalidArguments(array $pagination, string $message): void
    {
        $this->expectException(PaginationException::class);
        $this->expectExceptionMessage($message);

        $this->service->decodePaginationFields($pagination);
    }

    /**
     * Every permutation of the pagination arguments against a 10 row result set
     *
     * @return array<string, array{array<string, mixed>, int, int}>
     */
    public static function offsetAndLimitProvider(): array
    {
        $cursor = static fn (int $index): string => base64_encode((string) $index);

        return [
            // description => [pagination, expected offset, expected limit]
            'no arguments'             => [[], 0, 10],
            'first'                    => [['first' => 3], 0, 3],
            'first of zero'            => [['first' => 0], 0, 0],
            'first beyond the end'     => [['first' => 50], 0, 10],
            'last'                     => [['last' => 3], 7, 3],
            'last of zero'             => [['last' => 0], 10, 0],
            'last beyond the end'      => [['last' => 50], 0, 10],
            'after'                    => [['after' => $cursor(2)], 3, 7],
            'after the first row'      => [['after' => $cursor(0)], 1, 9],
            'after the last row'       => [['after' => $cursor(9)], 10, 0],
            'after beyond the end'     => [['after' => $cursor(50)], 10, 0],
            'before'                   => [['before' => $cursor(2)], 0, 2],
            'before the first row'     => [['before' => $cursor(0)], 0, 0],
            'before beyond the end'    => [['before' => $cursor(50)], 0, 10],
            'first and after'          => [['first' => 2, 'after' => $cursor(0)], 1, 2],
            'first and before'         => [['first' => 2, 'before' => $cursor(3)], 0, 2],
            'last and before'          => [['last' => 2, 'before' => $cursor(3)], 1, 2],
            'last and before the first row' => [['last' => 2, 'before' => $cursor(0)], 0, 0],
            'last and after'           => [['last' => 2, 'after' => $cursor(0)], 8, 2],
            'last and after within range' => [['last' => 2, 'after' => $cursor(8)], 9, 1],
            'after and before'         => [['after' => $cursor(0), 'before' => $cursor(3)], 1, 2],
            'after past before'        => [['after' => $cursor(5), 'before' => $cursor(2)], 6, 0],
            'first and last'           => [['first' => 4, 'last' => 2], 2, 2],
            'all four arguments'       => [
                ['first' => 4, 'last' => 2, 'after' => $cursor(0), 'before' => $cursor(8)],
                3,
                2,
            ],
        ];
    }

    /** @param array<string, mixed> $pagination */
    #[DataProvider('offsetAndLimitProvider')]
    public function testCalculateOffsetAndLimit(array $pagination, int $offset, int $limit): void
    {
        $result = $this->service->calculateOffsetAndLimit(
            $this->service->decodePaginationFields($pagination),
            100,
            10,
        );

        $this->assertSame(['offset' => $offset, 'limit' => $limit], $result);
    }

    public function testCalculateOffsetAndLimitCapsForwardRequestsAtTheDefaultLimit(): void
    {
        $result = $this->service->calculateOffsetAndLimit(
            $this->service->decodePaginationFields(['first' => 500]),
            100,
            1000,
        );

        $this->assertSame(['offset' => 0, 'limit' => 100], $result);
    }

    /**
     * A backward request keeps the end of the range when it is capped
     */
    public function testCalculateOffsetAndLimitCapsBackwardRequestsAtTheDefaultLimit(): void
    {
        $result = $this->service->calculateOffsetAndLimit(
            $this->service->decodePaginationFields(['last' => 500]),
            100,
            1000,
        );

        $this->assertSame(['offset' => 900, 'limit' => 100], $result);
    }

    public function testCalculateOffsetAndLimitWithAnEmptyResultSet(): void
    {
        $result = $this->service->calculateOffsetAndLimit(
            $this->service->decodePaginationFields(['last' => 5]),
            100,
            0,
        );

        $this->assertSame(['offset' => 0, 'limit' => 0], $result);
    }

    public function testCalculateRequestedOffsetAndLimit(): void
    {
        $this->assertSame(
            ['offset' => 0, 'limit' => 100],
            $this->service->calculateRequestedOffsetAndLimit(
                $this->service->decodePaginationFields([]),
                100,
            ),
        );

        $this->assertSame(
            ['offset' => 3, 'limit' => 2],
            $this->service->calculateRequestedOffsetAndLimit(
                $this->service->decodePaginationFields(['first' => 2, 'after' => base64_encode('2')]),
                100,
            ),
        );

        $this->assertSame(
            ['offset' => 8, 'limit' => 2],
            $this->service->calculateRequestedOffsetAndLimit(
                $this->service->decodePaginationFields(['last' => 2, 'before' => base64_encode('10')]),
                100,
            ),
        );
    }

    public function testBuildCursorsWithResults(): void
    {
        $this->assertEquals([
            'start' => base64_encode('10'),
            'end'   => base64_encode('29'),
        ], $this->service->buildCursors(10, 20));
    }

    public function testBuildCursorsWithASingleResult(): void
    {
        $this->assertEquals([
            'start' => base64_encode('4'),
            'end'   => base64_encode('4'),
        ], $this->service->buildCursors(4, 1));
    }

    /**
     * An empty page has no first or last node
     */
    public function testBuildCursorsWithNoResults(): void
    {
        $this->assertEquals([
            'start' => null,
            'end'   => null,
        ], $this->service->buildCursors(10, 0));
    }

    public function testBuildEdgesEmpty(): void
    {
        $this->assertEquals([], $this->service->buildEdges([], 0));
    }

    public function testBuildEdgesWithOffset(): void
    {
        $this->assertEquals([
            ['node' => 'a', 'cursor' => base64_encode('10')],
            ['node' => 'b', 'cursor' => base64_encode('11')],
            ['node' => 'c', 'cursor' => base64_encode('12')],
        ], $this->service->buildEdges(['a', 'b', 'c'], 10));
    }

    public function testBuildPaginationResponseFirstPage(): void
    {
        $edges    = $this->service->buildEdges(['a', 'b'], 0);
        $response = $this->service->buildPaginationResponse(
            $edges,
            $this->service->buildCursors(0, 2),
            100,
            0,
        );

        $this->assertSame(100, $response['totalCount']);
        $this->assertSame($edges, $response['edges']);
        $this->assertEquals([
            'endCursor'       => base64_encode('1'),
            'startCursor'     => base64_encode('0'),
            'hasNextPage'     => true,
            'hasPreviousPage' => false,
        ], $response['pageInfo']);
    }

    public function testBuildPaginationResponseMiddlePage(): void
    {
        $response = $this->service->buildPaginationResponse(
            $this->service->buildEdges(['a', 'b'], 10),
            $this->service->buildCursors(10, 2),
            100,
            10,
        );

        $this->assertEquals([
            'endCursor'       => base64_encode('11'),
            'startCursor'     => base64_encode('10'),
            'hasNextPage'     => true,
            'hasPreviousPage' => true,
        ], $response['pageInfo']);
    }

    public function testBuildPaginationResponseLastPage(): void
    {
        $response = $this->service->buildPaginationResponse(
            $this->service->buildEdges(['a', 'b'], 98),
            $this->service->buildCursors(98, 2),
            100,
            98,
        );

        $this->assertEquals([
            'endCursor'       => base64_encode('99'),
            'startCursor'     => base64_encode('98'),
            'hasNextPage'     => false,
            'hasPreviousPage' => true,
        ], $response['pageInfo']);
    }

    public function testBuildPaginationResponseSinglePage(): void
    {
        $response = $this->service->buildPaginationResponse(
            $this->service->buildEdges(['a'], 0),
            $this->service->buildCursors(0, 1),
            1,
            0,
        );

        $this->assertEquals([
            'endCursor'       => base64_encode('0'),
            'startCursor'     => base64_encode('0'),
            'hasNextPage'     => false,
            'hasPreviousPage' => false,
        ], $response['pageInfo']);
    }

    /**
     * An exhausted forward page must not advertise a next page
     */
    public function testBuildPaginationResponseExhaustedForwardPage(): void
    {
        $response = $this->service->buildPaginationResponse(
            [],
            $this->service->buildCursors(100, 0),
            100,
            100,
        );

        $this->assertEquals([
            'endCursor'       => null,
            'startCursor'     => null,
            'hasNextPage'     => false,
            'hasPreviousPage' => true,
        ], $response['pageInfo']);
    }

    /**
     * An empty page at the start of the result set still has a next page
     */
    public function testBuildPaginationResponseEmptyPageAtTheStart(): void
    {
        $response = $this->service->buildPaginationResponse(
            [],
            $this->service->buildCursors(0, 0),
            100,
            0,
        );

        $this->assertEquals([
            'endCursor'       => null,
            'startCursor'     => null,
            'hasNextPage'     => true,
            'hasPreviousPage' => false,
        ], $response['pageInfo']);
    }
}
