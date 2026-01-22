<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Pagination;

use function base64_decode;
use function base64_encode;

/**
 * Shared pagination logic for entities and collections
 */
class PaginationService
{
    /**
     * Decode pagination fields (after/before cursors)
     *
     * @param array<string, mixed> $pagination
     *
     * @return array<string, int>
     */
    public function decodePaginationFields(array $pagination): array
    {
        $paginationFields = [
            'first'  => 0,
            'last'   => 0,
            'before' => 0,
            'after'  => 0,
        ];

        foreach ($pagination as $field => $value) {
            $paginationFields[$field] = $value;

            if ($field === 'after') {
                $paginationFields[$field] = (int) base64_decode($value, true) + 1;
            }

            if ($field !== 'before') {
                continue;
            }

            $paginationFields[$field] = (int) base64_decode($value, true);
        }

        return $paginationFields;
    }

    /**
     * Calculate offset and limit from pagination fields
     *
     * @param array<string, int> $paginationFields
     *
     * @return array<string, int>
     */
    public function calculateOffsetAndLimit(
        array $paginationFields,
        int $defaultLimit,
        int|null $itemCount = null,
    ): array {
        $offset = 0;
        $limit  = $defaultLimit;

        $adjustedLimit = $paginationFields['first'] ?: $paginationFields['last'] ?: $limit;
        if ($adjustedLimit < $limit) {
            $limit = $adjustedLimit;
        }

        if ($paginationFields['after']) {
            $offset = $paginationFields['after'];
        } elseif ($paginationFields['before']) {
            $offset = $paginationFields['before'] - $limit;
        }

        if ($offset < 0) {
            $limit += $offset;
            $offset = 0;
        }

        // Handle 'last' without 'before' - requires item count
        if ($paginationFields['last'] && ! $paginationFields['before'] && $itemCount !== null) {
            $offset = $itemCount - $paginationFields['last'];
        }

        return [
            'offset' => $offset,
            'limit'  => $limit,
        ];
    }

    /**
     * Build cursors for pagination
     *
     * @return array<string, string|null>
     */
    public function buildCursors(
        int $offset,
        int $itemCount,
        int $resultCount,
    ): array {
        $cursors = [
            'start' => base64_encode((string) 0),
            'first' => null,
            'last'  => base64_encode((string) 0),
            'end'   => base64_encode((string) ($itemCount ? $itemCount - 1 : 0)),
        ];

        if ($resultCount > 0) {
            $cursors['first'] = base64_encode((string) $offset);
            $cursors['last']  = base64_encode((string) ($offset + $resultCount - 1));
            $cursors['start'] = $cursors['first'];
        }

        return $cursors;
    }

    /**
     * Build edges array with cursors for each item
     *
     * @param iterable<int, mixed> $items
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildEdges(iterable $items, int $offset): array
    {
        $edges = [];
        $index = 0;

        foreach ($items as $item) {
            $edges[] = [
                'node'   => $item,
                'cursor' => base64_encode((string) ($index + $offset)),
            ];

            $index++;
        }

        return $edges;
    }

    /**
     * Build final pagination response
     *
     * @param array<int, array<string, mixed>> $edges
     * @param array<string, string|null>       $cursors
     *
     * @return array<string, mixed>
     */
    public function buildPaginationResponse(
        array $edges,
        array $cursors,
        int $totalCount,
    ): array {
        return [
            'edges'      => $edges,
            'totalCount' => $totalCount,
            'pageInfo'   => [
                'endCursor'       => $cursors['last'],
                'startCursor'     => $cursors['start'],
                'hasNextPage'     => $cursors['end'] !== $cursors['last'],
                'hasPreviousPage' => $cursors['start'] !== base64_encode((string) 0),
            ],
        ];
    }
}
