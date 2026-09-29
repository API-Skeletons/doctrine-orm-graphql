<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

use function in_array;
use function iterator_to_array;
use function preg_match;

/**
 * Fetch a page of a query's entities
 *
 * A QueryBuilder event listener may fetch join a collection, which gives an
 * entity a row for each member of the collection.  A LIMIT counts rows, so
 * such a page is fetched by a Paginator, which limits the entities.
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
     * @return mixed[]
     */
    private function fetchPage(QueryBuilder $queryBuilder, int $offset, int $limit): array
    {
        $this->orderByIdentifier($queryBuilder);
        $queryBuilder->setFirstResult($offset);
        $queryBuilder->setMaxResults($limit);

        if (! $this->hasFetchJoin($queryBuilder)) {
            return $this->getResults($queryBuilder);
        }

        // Paginator is deprecated as of ORM 3.7 in favour of OffsetPaginator, which
        // does not exist in ORM 2.x or ORM < 3.7. Keep Paginator until those are dropped.

        /** @psalm-suppress DeprecatedClass */
        return iterator_to_array(new Paginator($queryBuilder->getQuery(), true), false);
    }

    /**
     * Whether the query selects a joined alias
     */
    private function hasFetchJoin(QueryBuilder $queryBuilder): bool
    {
        $aliases = [];

        /** @var array<string, list<Join>> $joins */
        $joins = $queryBuilder->getDQLPart('join');
        foreach ($joins as $rootJoins) {
            foreach ($rootJoins as $join) {
                $aliases[] = $join->getAlias();
            }
        }

        if ($aliases === []) {
            return false;
        }

        /** @var list<Select> $selects */
        $selects = $queryBuilder->getDQLPart('select');
        foreach ($selects as $select) {
            foreach ($select->getParts() as $part) {
                // An alias, or a partial or field of one
                if (
                    preg_match('/^\s*(?:partial\s+)?([A-Za-z_][A-Za-z0-9_]*)/i', (string) $part, $matches) === 1
                    && in_array($matches[1], $aliases, true)
                ) {
                    return true;
                }
            }
        }

        return false;
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
