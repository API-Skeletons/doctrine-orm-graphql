<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Cache;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

class QueryResultCacheTest extends TestCase
{
    private QueryResultCache $cache;

    public function setUp(): void
    {
        parent::setUp();

        $this->cache = new QueryResultCache();
    }

    public function testGetReturnsNullForUncachedQuery(): void
    {
        $qb    = self::$entityManager->createQueryBuilder();
        $query = $qb->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        $this->assertNull($this->cache->get($query));
    }

    public function testSetAndGetCachesResults(): void
    {
        $qb    = self::$entityManager->createQueryBuilder();
        $query = $qb->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        $results = ['test', 'data'];

        $this->cache->set($query, $results);
        $cachedResults = $this->cache->get($query);

        $this->assertEquals($results, $cachedResults);
    }

    public function testHasReturnsTrueForCachedQuery(): void
    {
        $qb    = self::$entityManager->createQueryBuilder();
        $query = $qb->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        $this->assertFalse($this->cache->has($query));

        $this->cache->set($query, ['test']);

        $this->assertTrue($this->cache->has($query));
    }

    public function testClearRemovesAllCachedResults(): void
    {
        $qb1    = self::$entityManager->createQueryBuilder();
        $query1 = $qb1->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        $qb2    = self::$entityManager->createQueryBuilder();
        $query2 = $qb2->select('p')
            ->from(Performance::class, 'p')
            ->getQuery();

        $this->cache->set($query1, ['data1']);
        $this->cache->set($query2, ['data2']);

        $this->assertTrue($this->cache->has($query1));
        $this->assertTrue($this->cache->has($query2));

        $this->cache->clear();

        $this->assertFalse($this->cache->has($query1));
        $this->assertFalse($this->cache->has($query2));
    }

    public function testDifferentQueriesHaveDifferentCacheKeys(): void
    {
        $qb1    = self::$entityManager->createQueryBuilder();
        $query1 = $qb1->select('a')
            ->from(Artist::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', 1)
            ->getQuery();

        $qb2    = self::$entityManager->createQueryBuilder();
        $query2 = $qb2->select('a')
            ->from(Artist::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', 2)
            ->getQuery();

        $this->cache->set($query1, ['artist1']);
        $this->cache->set($query2, ['artist2']);

        $this->assertEquals(['artist1'], $this->cache->get($query1));
        $this->assertEquals(['artist2'], $this->cache->get($query2));
    }

    public function testIdenticalQueriesShareCacheKey(): void
    {
        $qb1    = self::$entityManager->createQueryBuilder();
        $query1 = $qb1->select('a')
            ->from(Artist::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', 1)
            ->getQuery();

        $qb2    = self::$entityManager->createQueryBuilder();
        $query2 = $qb2->select('a')
            ->from(Artist::class, 'a')
            ->where('a.id = :id')
            ->setParameter('id', 1)
            ->getQuery();

        $this->cache->set($query1, ['shared_data']);

        // Query2 should get the same cached data
        $this->assertEquals(['shared_data'], $this->cache->get($query2));
    }

    public function testGetStatsReturnsCorrectStatistics(): void
    {
        $qb    = self::$entityManager->createQueryBuilder();
        $query = $qb->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        // Initial stats
        $stats = $this->cache->getStats();
        $this->assertEquals(0, $stats['size']);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0.0, $stats['hitRate']);

        // Miss
        $this->cache->get($query);
        $stats = $this->cache->getStats();
        $this->assertEquals(0, $stats['size']);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.0, $stats['hitRate']);

        // Add to cache
        $this->cache->set($query, ['data']);
        $stats = $this->cache->getStats();
        $this->assertEquals(1, $stats['size']);

        // Hit
        $this->cache->get($query);
        $stats = $this->cache->getStats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.5, $stats['hitRate']); // 1 hit / 2 total requests

        // Another hit
        $this->cache->get($query);
        $stats = $this->cache->getStats();
        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.6666666666666666, $stats['hitRate']); // 2 hits / 3 total requests
    }

    public function testClearResetsStats(): void
    {
        $qb    = self::$entityManager->createQueryBuilder();
        $query = $qb->select('a')
            ->from(Artist::class, 'a')
            ->getQuery();

        $this->cache->set($query, ['data']);
        $this->cache->get($query); // hit

        $stats = $this->cache->getStats();
        $this->assertGreaterThan(0, $stats['hits']);

        $this->cache->clear();

        $stats = $this->cache->getStats();
        $this->assertEquals(0, $stats['size']);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0.0, $stats['hitRate']);
    }
}
