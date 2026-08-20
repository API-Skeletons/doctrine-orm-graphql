<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use Closure;
use Doctrine\DBAL\Query\QueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;

use function count;
use function is_array;
use function method_exists;

/**
 * Build a resolver for a DBAL QueryBuilder.
 *
 * The QueryBuilder is modified with the offset and limit calculated from the
 * pagination argument then executed.  The result is returned in the GraphQL
 * Complete Connection Model.
 *
 * Because the QueryBuilder is captured when the schema is built it is cloned
 * for each resolution so repeated queries do not inherit the offset and limit
 * of a previous query.
 */
final class ResolveDbalFactory
{
    public function __construct(
        protected readonly Config $config,
        protected readonly PaginationService $paginationService,
    ) {
    }

    public function get(QueryBuilder $queryBuilder): Closure
    {
        return function (mixed $objectValue, array $args, mixed $context, ResolveInfo $info) use ($queryBuilder): array {
            return $this->buildPagination(clone $queryBuilder, $args);
        };
    }

    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     */
    public function buildPagination(QueryBuilder $queryBuilder, array $args): array
    {
        // Decode pagination fields
        /** @psalm-suppress MixedArgument */
        $paginationFields = $this->paginationService->decodePaginationFields(
            is_array($args['pagination'] ?? null) ? $args['pagination'] : [],
        );

        // The total number of rows must be known before the offset can be
        // calculated for a 'last' request without a 'before' cursor
        $itemCount = $this->getItemCount($queryBuilder);

        // Calculate offset and limit
        $offsetAndLimit = $this->paginationService->calculateOffsetAndLimit(
            $paginationFields,
            $this->config->getLimit(),
            $itemCount,
        );

        if ($offsetAndLimit['offset'] < 0) {
            $offsetAndLimit['offset'] = 0;
        }

        $queryBuilder->setFirstResult($offsetAndLimit['offset']);

        if ($offsetAndLimit['limit']) {
            $queryBuilder->setMaxResults($offsetAndLimit['limit']);
        }

        $results = $queryBuilder->executeQuery()->fetchAllAssociative();

        // Build edges
        $edges = $this->paginationService->buildEdges($results, $offsetAndLimit['offset']);

        // Build cursors
        $cursors = $this->paginationService->buildCursors(
            $offsetAndLimit['offset'],
            $itemCount,
            count($results),
        );

        // Build final pagination response
        return $this->paginationService->buildPaginationResponse(
            $edges,
            $cursors,
            $itemCount,
        );
    }

    /**
     * Count the rows the QueryBuilder matches without its offset and limit.
     *
     * The select is replaced with COUNT(*) on a clone of the QueryBuilder.
     * A QueryBuilder using GROUP BY or DISTINCT will not be counted correctly
     * by this strategy.
     */
    private function getItemCount(QueryBuilder $queryBuilder): int
    {
        $countQueryBuilder = clone $queryBuilder;

        $countQueryBuilder->setFirstResult(0);
        $countQueryBuilder->setMaxResults(null);
        $countQueryBuilder->select('COUNT(*)');

        $this->resetOrderBy($countQueryBuilder);

        return (int) $countQueryBuilder->fetchOne();
    }

    /**
     * An ORDER BY is invalid within an aggregate query on some platforms.
     *
     * The QueryBuilder is typed as an object so both the DBAL 3 and the
     * DBAL 4 method of removing the ORDER BY may be called.
     */
    private function resetOrderBy(object $queryBuilder): void
    {
        if (method_exists($queryBuilder, 'resetOrderBy')) {
            $queryBuilder->resetOrderBy();

            return;
        }

        if (! method_exists($queryBuilder, 'resetQueryPart')) {
            return;
        }

        $queryBuilder->resetQueryPart('orderBy');
    }
}
