<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Cache;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

/**
 * Filter parameters are named the same each time, so a filtered query is
 * served from the query result cache
 */
class FilteredQueryCacheTest extends TestCase
{
    public function testFilteredQueryIsServedFromTheCache(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['useQueryResultCache' => true]));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artist' => $driver->completeConnection(Artist::class)],
            ]),
        ]);

        $query = '{ artist(filter: { name: { contains: "Dead" } }) { edges { node { name } } } }';

        $first = GraphQL::executeQuery($schema, $query)->toArray();
        $cache = $driver->get(QueryResultCache::class);
        $this->assertSame(0, $cache->getStats()['hits']);

        $second = GraphQL::executeQuery($schema, $query)->toArray();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $cache->getStats()['hits']);
    }
}
