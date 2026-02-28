<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Cache;

use Doctrine\ORM\Query;

use function count;
use function implode;
use function is_array;
use function md5;
use function serialize;

/**
 * Request-scoped cache for query results.
 *
 * Caches query results based on SQL + parameters signature to prevent
 * duplicate database queries within a single GraphQL request. This is
 * particularly useful for:
 * - Circular references in the graph
 * - Queries accessing the same entity multiple times
 * - Duplicate association queries
 *
 * The cache is stored in memory and is automatically cleared after
 * the request completes.
 */
class QueryResultCache
{
    /** @var array<string, mixed[]> */
    private array $cache = [];

    /** @var array<string, int> */
    private array $hits = [];

    /** @var array<string, int> */
    private array $misses = [];

    /**
     * Get cached results for a query if available
     *
     * @return mixed[]|null Returns cached results or null if not cached
     */
    public function get(Query $query): array|null
    {
        $cacheKey = $this->getCacheKey($query);

        if (isset($this->cache[$cacheKey])) {
            $this->hits[$cacheKey] = ($this->hits[$cacheKey] ?? 0) + 1;

            return $this->cache[$cacheKey];
        }

        $this->misses[$cacheKey] = ($this->misses[$cacheKey] ?? 0) + 1;

        return null;
    }

    /**
     * Store query results in cache
     *
     * @param mixed[] $results
     */
    public function set(Query $query, array $results): void
    {
        $cacheKey               = $this->getCacheKey($query);
        $this->cache[$cacheKey] = $results;
    }

    /**
     * Check if query results are cached
     */
    public function has(Query $query): bool
    {
        return isset($this->cache[$this->getCacheKey($query)]);
    }

    /**
     * Clear all cached results
     */
    public function clear(): void
    {
        $this->cache  = [];
        $this->hits   = [];
        $this->misses = [];
    }

    /**
     * Get cache statistics
     *
     * @return array{size: int, hits: int, misses: int, hitRate: float}
     */
    public function getStats(): array
    {
        $totalHits   = 0;
        $totalMisses = 0;

        foreach ($this->hits as $hits) {
            $totalHits += $hits;
        }

        foreach ($this->misses as $misses) {
            $totalMisses += $misses;
        }

        $totalRequests = $totalHits + $totalMisses;
        $hitRate       = $totalRequests > 0 ? $totalHits / $totalRequests : 0.0;

        return [
            'size' => count($this->cache),
            'hits' => $totalHits,
            'misses' => $totalMisses,
            'hitRate' => $hitRate,
        ];
    }

    /**
     * Generate cache key from query SQL and parameters
     */
    private function getCacheKey(Query $query): string
    {
        $sql        = $query->getSQL();
        $sql        = is_array($sql) ? implode(';', $sql) : $sql;
        $parameters = $query->getParameters()->toArray();

        // Normalize parameters for consistent cache keys
        $normalizedParams = [];
        foreach ($parameters as $param) {
            $normalizedParams[$param->getName()] = $param->getValue();
        }

        return md5($sql . serialize($normalizedParams));
    }
}
