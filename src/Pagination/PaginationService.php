<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Pagination as PaginationException;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

use function array_slice;
use function array_values;
use function base64_decode;
use function base64_encode;
use function count;
use function ctype_digit;
use function filter_var;
use function is_int;
use function is_string;
use function ltrim;
use function max;
use function min;

use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

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
     * The pagination arguments of the GraphQL Complete Connection Model.
     * These are top level arguments of a connection field.
     *
     * @return array<string, array{type: Type, description: string}>
     */
    public function getArguments(): array
    {
        return [
            'first' => [
                'type'        => Type::int(),
                'description' => 'Takes a non-negative integer.',
            ],
            'after' => [
                'type'        => Type::string(),
                'description' => 'Takes the cursor type.',
            ],
            'last' => [
                'type'        => Type::int(),
                'description' => 'Takes a non-negative integer.',
            ],
            'before' => [
                'type'        => Type::string(),
                'description' => 'Takes the cursor type.',
            ],
        ];
    }

    /**
     * Decode the pagination arguments into integers
     *
     * A field which was not supplied is returned as null so that a cursor for
     * index zero may be distinguished from an absent argument.  The `after`
     * value is returned as the index of the first row to return, which is one
     * past the cursor it was decoded from.
     *
     * @param mixed[] $pagination The connection field arguments.  Arguments other
     *                           than first, after, last and before are ignored.
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
            // The first row is the one after the cursor's, which must also be an int
            $paginationFields['after'] = $this->decodeCursor('after', $pagination['after'], PHP_INT_MAX - 1) + 1;
        }

        if (isset($pagination['before'])) {
            $paginationFields['before'] = $this->decodeCursor('before', $pagination['before'], PHP_INT_MAX);
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
     * Resolve a page of a connection.  The rows are counted only when the
     * page needs it: for totalCount, for a backward page, and for first: 0,
     * whose page has no rows to show whether there are more.  A forward page
     * is fetched with one row more than it holds instead, which tells whether
     * there is a next page.
     *
     * @param array{first: int|null, last: int|null, before: int|null, after: int|null} $paginationFields
     * @param bool                                                                      $needsCount       Whether the page needs the count; see needsCount()
     * @param callable(): int                                                           $count            The number of rows
     * @param callable(int, int): array<array-key, mixed>                               $fetch            The rows at an offset, up to a limit
     *
     * @return array<string, mixed>
     */
    public function paginate(
        array $paginationFields,
        int $defaultLimit,
        bool $needsCount,
        callable $count,
        callable $fetch,
    ): array {
        if ($needsCount) {
            $itemCount = $count();
            $page      = $this->calculateOffsetAndLimit($paginationFields, $defaultLimit, $itemCount);
            $results   = $page['limit'] > 0 ? array_values($fetch($page['offset'], $page['limit'])) : [];

            return $this->buildPaginationResponse(
                $this->buildEdges($results, $page['offset']),
                $this->buildCursors($page['offset'], count($results)),
                $itemCount,
                $page['offset'],
            );
        }

        $page    = $this->calculateForwardOffsetAndLimit($paginationFields, $defaultLimit);
        $results = array_values($fetch($page['offset'], $page['limit'] + 1));

        $hasNextPage = count($results) > $page['limit'];
        $results     = array_slice($results, 0, $page['limit']);

        return $this->buildPaginationResponse(
            $this->buildEdges($results, $page['offset']),
            $this->buildCursors($page['offset'], count($results)),
            null,
            $page['offset'],
            $hasNextPage,
        );
    }

    /**
     * Whether the rows must be counted to resolve a page: for totalCount, for
     * a backward page, and for first: 0.  Without the ResolveInfo they are.
     *
     * @param array{first: int|null, last: int|null, before: int|null, after: int|null} $paginationFields
     */
    public function needsCount(array $paginationFields, ResolveInfo|null $info): bool
    {
        if (
            $info === null
            || $paginationFields['last'] !== null
            || $paginationFields['before'] !== null
            || $paginationFields['first'] === 0
        ) {
            return true;
        }

        return isset($info->getFieldSelection()['totalCount']);
    }

    /**
     * The offset and limit of a forward page, which need no count: from the
     * after cursor, up to first rows within the limit
     *
     * @param array{first: int|null, last: int|null, before: int|null, after: int|null} $paginationFields
     *
     * @return array{offset: int, limit: int}
     */
    public function calculateForwardOffsetAndLimit(array $paginationFields, int $defaultLimit): array
    {
        $limit = $paginationFields['first'] ?? $defaultLimit;

        if ($defaultLimit > 0) {
            $limit = min($limit, $defaultLimit);
        }

        return [
            'offset' => $paginationFields['after'] ?? 0,
            'limit'  => $limit,
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
     * A page resolved without counting its rows has no totalCount, which was
     * not requested, and is given whether it has a next page.
     *
     * @param array<int, array<string, mixed>>            $edges
     * @param array{start: string|null, end: string|null} $cursors
     *
     * @return array<string, mixed>
     */
    public function buildPaginationResponse(
        array $edges,
        array $cursors,
        int|null $totalCount,
        int $offset,
        bool|null $hasNextPage = null,
    ): array {
        $resultCount = count($edges);

        return [
            'edges'      => $edges,
            'totalCount' => $totalCount,
            'pageInfo'   => [
                'endCursor'       => $cursors['end'],
                'startCursor'     => $cursors['start'],
                'hasNextPage'     => $hasNextPage ?? $offset + $resultCount < (int) $totalCount,
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
     * Decode a cursor into the row index it represents.  A cursor is a row's
     * position, not its identifier, so it is an int: an index larger than
     * $maxIndex is not a valid cursor, rather than an offset no query takes.
     *
     * @throws PaginationException
     */
    private function decodeCursor(string $field, mixed $value, int $maxIndex): int
    {
        $decoded = is_string($value) ? base64_decode($value, true) : false;
        $index   = $decoded !== false && ctype_digit($decoded)
            ? filter_var(ltrim($decoded, '0') ?: '0', FILTER_VALIDATE_INT, ['options' => ['max_range' => $maxIndex]])
            : false;

        if ($index === false) {
            throw new PaginationException(
                'Pagination argument "' . $field . '" is not a valid cursor.',
            );
        }

        return $index;
    }
}
