<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use ReflectionProperty;

use function assert;
use function method_exists;

/**
 * Build a resolver for a DBAL QueryBuilder.
 *
 * The QueryBuilder is modified with the offset and limit calculated from the
 * pagination arguments then executed.  The result is returned in the GraphQL
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
            return $this->buildPagination(clone $queryBuilder, $args, $info);
        };
    }

    /**
     * @param mixed[] $args The connection field arguments
     *
     * @return mixed[]
     */
    public function buildPagination(QueryBuilder $queryBuilder, array $args, ResolveInfo|null $info = null): array
    {
        $paginationFields = $this->paginationService->decodePaginationFields($args);

        return $this->paginationService->paginate(
            $paginationFields,
            $this->config->getLimit(),
            $this->paginationService->needsCount($paginationFields, $info),
            fn (): int => $this->getItemCount($queryBuilder),
            static function (int $offset, int $limit) use ($queryBuilder): array {
                $queryBuilder->setFirstResult($offset);
                $queryBuilder->setMaxResults($limit);

                return $queryBuilder->executeQuery()->fetchAllAssociative();
            },
        );
    }

    /**
     * Count the rows the QueryBuilder matches without its offset and limit.
     *
     * The query is counted as a subquery, so a query using GROUP BY, DISTINCT
     * or HAVING is counted by its rows rather than by the rows it groups.
     */
    private function getItemCount(QueryBuilder $queryBuilder): int
    {
        $countQueryBuilder = clone $queryBuilder;

        $countQueryBuilder->setFirstResult(0);
        $countQueryBuilder->setMaxResults(null);

        $this->resetOrderBy($countQueryBuilder);

        return (int) $this->getConnection($queryBuilder)->fetchOne(
            'SELECT COUNT(*) FROM (' . $countQueryBuilder->getSQL() . ') dbal_count',
            $countQueryBuilder->getParameters(),
            $countQueryBuilder->getParameterTypes(),
        );
    }

    /**
     * The connection of the QueryBuilder.  DBAL 4 has no getter for it, and
     * it may not be the entity manager's connection.
     */
    private function getConnection(QueryBuilder $queryBuilder): Connection
    {
        $connection = (new ReflectionProperty(QueryBuilder::class, 'connection'))->getValue($queryBuilder);
        assert($connection instanceof Connection);

        return $connection;
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
