<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

use function array_diff;
use function iterator_to_array;

/**
 * Fetch a page of a query's entities
 *
 * A QueryBuilder event listener may join a collection, fetched or not, which
 * gives an entity a row for each member of the collection.  A LIMIT counts
 * rows, so a page of a query with a join is fetched by a Paginator, which
 * limits the entities.
 *
 * @property-read Config           $config
 * @property-read QueryResultCache $queryResultCache
 */
trait FetchPage
{
    use OrderByIdentifier;

    /**
     * Fetch a page of a query, ordered by identifier after any other ordering
     *
     * @param list<string> $ownJoins The aliases of joins which give an entity one row, which need no Paginator
     *
     * @return mixed[]
     */
    private function fetchPage(QueryBuilder $queryBuilder, int $offset, int $limit, array $ownJoins = []): array
    {
        $this->orderByIdentifier($queryBuilder);
        $queryBuilder->setFirstResult($offset);
        $queryBuilder->setMaxResults($limit);

        if (array_diff($this->getJoinAliases($queryBuilder), $ownJoins) === []) {
            return $this->getResults($queryBuilder);
        }

        // Paginator is deprecated as of ORM 3.7 in favour of OffsetPaginator, which
        // does not exist in ORM 2.x or ORM < 3.7. Keep Paginator until those are dropped.

        /** @psalm-suppress DeprecatedClass */
        return iterator_to_array(new Paginator($queryBuilder->getQuery(), true), false);
    }

    /**
     * The aliases of the query's joins
     *
     * @return list<string>
     */
    private function getJoinAliases(QueryBuilder $queryBuilder): array
    {
        $aliases = [];

        /** @var array<string, list<Join>> $joins */
        $joins = $queryBuilder->getDQLPart('join');
        foreach ($joins as $rootJoins) {
            foreach ($rootJoins as $join) {
                $aliases[] = (string) $join->getAlias();
            }
        }

        return $aliases;
    }

    /**
     * Fetch the rows for the QueryBuilder, using the query result cache when enabled
     *
     * @return mixed[]
     */
    private function getResults(QueryBuilder $queryBuilder): array
    {
        $query = $queryBuilder->getQuery();

        if (! $this->config->getUseQueryResultCache()) {
            /** @psalm-suppress MixedReturnStatement */
            return $query->getResult();
        }

        $cachedResults = $this->queryResultCache->get($query);

        if ($cachedResults !== null) {
            return $cachedResults;
        }

        /** @psalm-suppress MixedAssignment */
        $results = $query->getResult();
        /** @psalm-suppress MixedArgument */
        $this->queryResultCache->set($query, $results);

        return $results;
    }
}
