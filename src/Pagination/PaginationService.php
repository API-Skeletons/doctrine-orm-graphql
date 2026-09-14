<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Pagination as PaginationException;

use function base64_decode;
use function base64_encode;
use function count;
use function ctype_digit;
use function is_int;
use function is_string;
use function max;
use function min;

/**
 * Shared pagination logic for entities, collections and DBAL queries
 *
 * Cursors are the base64 encoded, zero based index of a row within the full
 * result set.  The `after` cursor is exclusive and the `before` cursor is
 * exclusive, matching the GraphQL Complete Connection Model.
 */
final class PaginationService
{
    /**
     * Decode the pagination argument into integers
     *
     * A field which was not supplied is returned as null so that a cursor for
     * index zero may be distinguished from an absent argument.  The `after`
     * value is returned as the index of the first row to return, which is one
     * past the cursor it was decoded from.
     *
     * @param array<string, mixed> $pagination
     *
     * @return array{first: int|null, last: int|null, before: int|null, after: int|null}
     *
     * @throws PaginationException When an argument is negative or a cursor cannot be decoded.
     */
    public function decodePaginationFields(array $pagination): array
    {
        $paginationFields = [
            'first'  => null,
            'last'   => null,
            'before' => null,
            'after'  => null,
        ];

        foreach (['first', 'last'] as $field) {
            if (! isset($pagination[$field])) {
                continue;
            }

            $paginationFields[$field] = $this->decodeCount($field, $pagination[$field]);
        }

        if (isset($pagination['after'])) {
            $paginationFields['after'] = $this->decodeCursor('after', $pagination['after']) + 1;
        }

        if (isset($pagination['before'])) {
            $paginationFields['before'] = $this->decodeCursor('before', $pagination['before']);
        }

        return $paginationFields;
    }

    /**
     * Resolve the pagination arguments into an offset and a limit
     *
     * The GraphQL Complete Connection Model algorithm is applied to the range
     * [0, $itemCount): `after` and `before` narrow the range, then `first`
     * narrows it from the end and `last` narrows it from the start.  Every
     * combination of arguments is therefore well defined and no argument is
     * silently discarded.
     *
     * A limit of zero means the request cannot match any row and the query
     * should not be executed.
     *
     * @param array{first: int|null, last: int|null, before: int|null, after: int|null} $paginationFields
     *
     * @return array{offset: int, limit: int}
     */
    public function calculateOffsetAndLimit(
        array $paginationFields,
        int $defaultLimit,
        int $itemCount,
    ): array {
        $itemCount = max($itemCount, 0);
        $start     = 0;
        $end       = $itemCount;

        if ($paginationFields['after'] !== null) {
            $start = min(max($start, $paginationFields['after']), $itemCount);
        }

        if ($paginationFields['before'] !== null) {
            $end = min($end, $paginationFields['before']);
        }

        // A before cursor at or below the offset leaves nothing to return
        $end = max($end, $start);

        if ($paginationFields['first'] !== null) {
            $end = min($end, $start + $paginationFields['first']);
        }

        if ($paginationFields['last'] !== null) {
            $start = max($start, $end - $paginationFields['last']);
        }

        // The configured limit is a hard cap on the rows a single query returns
        if ($defaultLimit > 0 && $end - $start > $defaultLimit) {
            if ($paginationFields['last'] !== null && $paginationFields['first'] === null) {
                // A backward request keeps the end of the range
                $start = $end - $defaultLimit;
            } else {
                $end = $start + $defaultLimit;
            }
        }

        return [
            'offset' => $start,
            'limit'  => $end - $start,
        ];
    }

    /**
     * Resolve the offset and limit a request asks for, before the rows are counted
     *
     * The QueryBuilder event is dispatched before the count query runs so that
     * a listener may modify the QueryBuilder.  These values are the window the
     * client requested rather than the window finally queried; a backward
     * (`last`) request without a `before` cursor reports an offset of zero
     * because its offset cannot be known until the rows have been counted.
     *
     * @param array{first: int|null, last: int|null, before: int|null, after: int|null} $paginationFields
     *
     * @return array{offset: int, limit: int}
     */
    public function calculateRequestedOffsetAndLimit(
        array $paginationFields,
        int $defaultLimit,
    ): array {
        $limit = $paginationFields['first'] ?? $paginationFields['last'] ?? $defaultLimit;

        if ($defaultLimit > 0) {
            $limit = min($limit, $defaultLimit);
        }

        $offset = 0;

        if ($paginationFields['after'] !== null) {
            $offset = $paginationFields['after'];
        } elseif ($paginationFields['before'] !== null) {
            $offset = max($paginationFields['before'] - $limit, 0);
        }

        return [
            'offset' => $offset,
            'limit'  => $limit,
        ];
    }

    /**
     * Build the start and end cursors for a page
     *
     * An empty page has no first or last node so both cursors are null.
     *
     * @return array{start: string|null, end: string|null}
     */
    public function buildCursors(int $offset, int $resultCount): array
    {
        if ($resultCount < 1) {
            return [
                'start' => null,
                'end'   => null,
            ];
        }

        return [
            'start' => base64_encode((string) $offset),
            'end'   => base64_encode((string) ($offset + $resultCount - 1)),
        ];
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

        /** @psalm-suppress MixedAssignment */
        foreach ($items as $item) {
            /** @psalm-suppress MixedAssignment */
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
     * The page flags are derived from the offset and the row count rather than
     * from the cursors so that an empty page reports its position correctly.
     *
     * @param array<int, array<string, mixed>>            $edges
     * @param array{start: string|null, end: string|null} $cursors
     *
     * @return array<string, mixed>
     */
    public function buildPaginationResponse(
        array $edges,
        array $cursors,
        int $totalCount,
        int $offset,
    ): array {
        $resultCount = count($edges);

        return [
            'edges'      => $edges,
            'totalCount' => $totalCount,
            'pageInfo'   => [
                'endCursor'       => $cursors['end'],
                'startCursor'     => $cursors['start'],
                'hasNextPage'     => $offset + $resultCount < $totalCount,
                'hasPreviousPage' => $offset > 0,
            ],
        ];
    }

    /**
     * Validate a non negative integer pagination argument
     *
     * @throws PaginationException
     */
    private function decodeCount(string $field, mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new PaginationException(
                'Pagination argument "' . $field . '" must be a non-negative integer.',
            );
        }

        $count = (int) $value;

        if ($count < 0) {
            throw new PaginationException(
                'Pagination argument "' . $field . '" must be a non-negative integer.',
            );
        }

        return $count;
    }

    /**
     * Decode a cursor into the row index it represents
     *
     * @throws PaginationException
     */
    private function decodeCursor(string $field, mixed $value): int
    {
        $decoded = is_string($value) ? base64_decode($value, true) : false;

        if ($decoded === false || ! ctype_digit($decoded)) {
            throw new PaginationException(
                'Pagination argument "' . $field . '" is not a valid cursor.',
            );
        }

        return (int) $decoded;
    }
}
