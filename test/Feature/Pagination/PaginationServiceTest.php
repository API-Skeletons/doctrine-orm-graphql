<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
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
        $result = $this->service->decodePaginationFields([]);

        $this->assertEquals([
            'first'  => 0,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ], $result);
    }

    public function testDecodePaginationFieldsWithFirst(): void
    {
        $result = $this->service->decodePaginationFields(['first' => 10]);

        $this->assertEquals([
            'first'  => 10,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ], $result);
    }

    public function testDecodePaginationFieldsWithLast(): void
    {
        $result = $this->service->decodePaginationFields(['last' => 5]);

        $this->assertEquals([
            'first'  => 0,
            'last'   => 5,
            'before' => 0,
            'after'  => 0,
        ], $result);
    }

    public function testDecodePaginationFieldsWithAfterCursor(): void
    {
        $cursor = base64_encode('9');
        $result = $this->service->decodePaginationFields(['after' => $cursor]);

        $this->assertEquals([
            'first'  => 0,
            'last'   => 0,
            'before' => 0,
            'after'  => 10, // 9 + 1
        ], $result);
    }

    public function testDecodePaginationFieldsWithBeforeCursor(): void
    {
        $cursor = base64_encode('20');
        $result = $this->service->decodePaginationFields(['before' => $cursor]);

        $this->assertEquals([
            'first'  => 0,
            'last'   => 0,
            'before' => 20,
            'after'  => 0,
        ], $result);
    }

    public function testCalculateOffsetAndLimitDefault(): void
    {
        $paginationFields = [
            'first'  => 0,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 0,
            'limit'  => 100,
        ], $result);
    }

    public function testCalculateOffsetAndLimitWithFirst(): void
    {
        $paginationFields = [
            'first'  => 10,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 0,
            'limit'  => 10,
        ], $result);
    }

    public function testCalculateOffsetAndLimitWithLast(): void
    {
        $paginationFields = [
            'first'  => 0,
            'last'   => 5,
            'before' => 0,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100, 50);

        $this->assertEquals([
            'offset' => 45, // 50 - 5
            'limit'  => 5,
        ], $result);
    }

    public function testCalculateOffsetAndLimitWithAfter(): void
    {
        $paginationFields = [
            'first'  => 10,
            'last'   => 0,
            'before' => 0,
            'after'  => 20,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 20,
            'limit'  => 10,
        ], $result);
    }

    public function testCalculateOffsetAndLimitWithBefore(): void
    {
        $paginationFields = [
            'first'  => 10,
            'last'   => 0,
            'before' => 30,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 20, // 30 - 10
            'limit'  => 10,
        ], $result);
    }

    public function testCalculateOffsetAndLimitNegativeOffsetAdjustment(): void
    {
        $paginationFields = [
            'first'  => 10,
            'last'   => 0,
            'before' => 5,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 0,  // Adjusted from -5
            'limit'  => 5,  // Adjusted from 10
        ], $result);
    }

    public function testCalculateOffsetAndLimitFirstExceedsDefault(): void
    {
        $paginationFields = [
            'first'  => 200,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ];

        $result = $this->service->calculateOffsetAndLimit($paginationFields, 100);

        $this->assertEquals([
            'offset' => 0,
            'limit'  => 100, // Capped at default limit
        ], $result);
    }

    public function testBuildCursorsWithResults(): void
    {
        $result = $this->service->buildCursors(10, 100, 20);

        $this->assertEquals([
            'start' => base64_encode('10'),
            'first' => base64_encode('10'),
            'last'  => base64_encode('29'), // 10 + 20 - 1
            'end'   => base64_encode('99'),
        ], $result);
    }

    public function testBuildCursorsWithNoResults(): void
    {
        $result = $this->service->buildCursors(0, 0, 0);

        $this->assertEquals([
            'start' => base64_encode('0'),
            'first' => null,
            'last'  => base64_encode('0'),
            'end'   => base64_encode('0'),
        ], $result);
    }

    public function testBuildCursorsAtStart(): void
    {
        $result = $this->service->buildCursors(0, 100, 10);

        $this->assertEquals([
            'start' => base64_encode('0'),
            'first' => base64_encode('0'),
            'last'  => base64_encode('9'),
            'end'   => base64_encode('99'),
        ], $result);
    }

    public function testBuildEdgesEmpty(): void
    {
        $result = $this->service->buildEdges([], 0);

        $this->assertEmpty($result);
    }

    public function testBuildEdgesWithItems(): void
    {
        $items  = ['item1', 'item2', 'item3'];
        $result = $this->service->buildEdges($items, 10);

        $this->assertCount(3, $result);
        $this->assertEquals([
            ['node' => 'item1', 'cursor' => base64_encode('10')],
            ['node' => 'item2', 'cursor' => base64_encode('11')],
            ['node' => 'item3', 'cursor' => base64_encode('12')],
        ], $result);
    }

    public function testBuildPaginationResponseComplete(): void
    {
        $edges   = [
            ['node' => 'item1', 'cursor' => base64_encode('10')],
            ['node' => 'item2', 'cursor' => base64_encode('11')],
        ];
        $cursors = [
            'start' => base64_encode('10'),
            'first' => base64_encode('10'),
            'last'  => base64_encode('11'),
            'end'   => base64_encode('99'),
        ];

        $result = $this->service->buildPaginationResponse($edges, $cursors, 100);

        $this->assertEquals([
            'edges'      => $edges,
            'totalCount' => 100,
            'pageInfo'   => [
                'endCursor'       => base64_encode('11'),
                'startCursor'     => base64_encode('10'),
                'hasNextPage'     => true,
                'hasPreviousPage' => true,
            ],
        ], $result);
    }

    public function testBuildPaginationResponseFirstPage(): void
    {
        $edges   = [
            ['node' => 'item1', 'cursor' => base64_encode('0')],
            ['node' => 'item2', 'cursor' => base64_encode('1')],
        ];
        $cursors = [
            'start' => base64_encode('0'),
            'first' => base64_encode('0'),
            'last'  => base64_encode('1'),
            'end'   => base64_encode('99'),
        ];

        $result = $this->service->buildPaginationResponse($edges, $cursors, 100);

        $this->assertEquals([
            'edges'      => $edges,
            'totalCount' => 100,
            'pageInfo'   => [
                'endCursor'       => base64_encode('1'),
                'startCursor'     => base64_encode('0'),
                'hasNextPage'     => true,
                'hasPreviousPage' => false,
            ],
        ], $result);
    }

    public function testBuildPaginationResponseLastPage(): void
    {
        $edges   = [
            ['node' => 'item1', 'cursor' => base64_encode('98')],
            ['node' => 'item2', 'cursor' => base64_encode('99')],
        ];
        $cursors = [
            'start' => base64_encode('98'),
            'first' => base64_encode('98'),
            'last'  => base64_encode('99'),
            'end'   => base64_encode('99'),
        ];

        $result = $this->service->buildPaginationResponse($edges, $cursors, 100);

        $this->assertEquals([
            'edges'      => $edges,
            'totalCount' => 100,
            'pageInfo'   => [
                'endCursor'       => base64_encode('99'),
                'startCursor'     => base64_encode('98'),
                'hasNextPage'     => false,
                'hasPreviousPage' => true,
            ],
        ], $result);
    }

    public function testBuildPaginationResponseSinglePage(): void
    {
        $edges   = [
            ['node' => 'item1', 'cursor' => base64_encode('0')],
        ];
        $cursors = [
            'start' => base64_encode('0'),
            'first' => base64_encode('0'),
            'last'  => base64_encode('0'),
            'end'   => base64_encode('0'),
        ];

        $result = $this->service->buildPaginationResponse($edges, $cursors, 1);

        $this->assertEquals([
            'edges'      => $edges,
            'totalCount' => 1,
            'pageInfo'   => [
                'endCursor'       => base64_encode('0'),
                'startCursor'     => base64_encode('0'),
                'hasNextPage'     => false,
                'hasPreviousPage' => false,
            ],
        ], $result);
    }
}
