<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Cache;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Events;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;
use function gc_collect_cycles;

class QueryResultCacheIntegrationTest extends TestCase
{
    public function testQueryResultCacheReducesDatabaseQueries(): void
    {
        $config = new Config(['useQueryResultCache' => true]);

        $driver = new Driver(self::$entityManager, $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '
            {
                artists {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        ';

        // Execute query first time
        $result1 = GraphQL::executeQuery($schema, $query);
        $this->assertEmpty($result1->errors);

        // Get cache stats after first execution
        $cache = $driver->get(QueryResultCache::class);
        $stats = $cache->getStats();

        // We should have at least one miss (the initial query)
        $this->assertGreaterThan(0, $stats['misses']);
        $this->assertEquals(0, $stats['hits']);

        // Clear stats for second test
        $cache->clear();

        // Execute the same query twice in a row
        GraphQL::executeQuery($schema, $query);
        GraphQL::executeQuery($schema, $query);

        $stats = $cache->getStats();

        // The second execution is served from the cache
        $this->assertGreaterThan(0, $stats['hits']);
    }

    public function testQueryResultCacheDisabledByDefault(): void
    {
        $config = new Config(['useQueryResultCache' => false]);

        $driver = new Driver(self::$entityManager, $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '
            {
                artists {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        ';

        // Execute query twice
        GraphQL::executeQuery($schema, $query);
        GraphQL::executeQuery($schema, $query);

        $cache = $driver->get(QueryResultCache::class);
        $stats = $cache->getStats();

        // Cache should not be used when disabled
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
        $this->assertEquals(0, $stats['size']);
    }

    public function testQueryResultCacheWithCollections(): void
    {
        $config = new Config(['useQueryResultCache' => true]);

        $driver = new Driver(self::$entityManager, $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '
            {
                artists {
                    edges {
                        node {
                            id
                            name
                            performances {
                                edges {
                                    node {
                                        venue
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $this->assertEmpty($result->errors);

        $cache = $driver->get(QueryResultCache::class);
        $stats = $cache->getStats();

        // Cache should be populated after executing the query
        $this->assertGreaterThan(0, $stats['size']);
    }

    public function testDifferentFiltersUseDifferentCacheEntries(): void
    {
        $config = new Config(['useQueryResultCache' => true]);

        $driver = new Driver(self::$entityManager, $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query1 = '
            {
                artists(filter: { name: { contains: "Grateful" } }) {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        ';

        $query2 = '
            {
                artists(filter: { name: { contains: "Phish" } }) {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        ';

        $result1 = GraphQL::executeQuery($schema, $query1);
        $result2 = GraphQL::executeQuery($schema, $query2);

        $this->assertEmpty($result1->errors);
        $this->assertEmpty($result2->errors);

        $cache = $driver->get(QueryResultCache::class);
        $stats = $cache->getStats();

        // Different filters should create different cache entries
        $this->assertGreaterThan(1, $stats['size']);
    }

    public function testCacheCanBeCleared(): void
    {
        $config = new Config(['useQueryResultCache' => true]);

        $driver = new Driver(self::$entityManager, $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '
            {
                artists {
                    edges {
                        node {
                            id
                            name
                        }
                    }
                }
            }
        ';

        GraphQL::executeQuery($schema, $query);

        $cache = $driver->get(QueryResultCache::class);
        $stats = $cache->getStats();
        $this->assertGreaterThan(0, $stats['size']);

        $cache->clear();

        $stats = $cache->getStats();
        $this->assertEquals(0, $stats['size']);
        $this->assertEquals(0, $stats['hits']);
        $this->assertEquals(0, $stats['misses']);
    }

    private function schema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);
    }

    private function listenerCount(): int
    {
        return count(self::$entityManager->getEventManager()->getListeners(Events::onClear));
    }

    /**
     * Rows changed outside the entity manager are seen once it is cleared
     */
    public function testClearingTheEntityManagerClearsTheCache(): void
    {
        $driver = new Driver(self::$entityManager, new Config(['useQueryResultCache' => true]));
        $schema = $this->schema($driver);
        $query  = '{ artists(filter: { name: { eq: "Phish" } }) { edges { node { id name } } } }';

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertSame('Phish', $result['data']['artists']['edges'][0]['node']['name']);

        self::$entityManager->getConnection()->executeStatement(
            "UPDATE artist SET name = 'Phish' WHERE name = 'Grateful Dead'",
        );
        self::$entityManager->clear();

        $this->assertSame(0, $driver->get(QueryResultCache::class)->getStats()['size']);

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertCount(2, $result['data']['artists']['edges']);
    }

    /**
     * A flush, as by a mutation, clears the cache
     */
    public function testFlushClearsTheCache(): void
    {
        $driver = new Driver(self::$entityManager, new Config(['useQueryResultCache' => true]));
        $schema = $this->schema($driver);

        GraphQL::executeQuery($schema, '{ artists { edges { node { id name } } } }');

        $cache = $driver->get(QueryResultCache::class);
        $this->assertGreaterThan(0, $cache->getStats()['size']);

        $artist = self::$entityManager->getRepository(Artist::class)->findOneBy(['name' => 'Phish']);
        $this->assertInstanceOf(Artist::class, $artist);
        $artist->setName('Trey');
        self::$entityManager->flush();

        $this->assertSame(0, $cache->getStats()['size']);

        $result = GraphQL::executeQuery($schema, '{ artists(filter: { name: { eq: "Phish" } }) { edges { node { id } } } }')
            ->toArray();
        $this->assertCount(0, $result['data']['artists']['edges']);
    }

    /**
     * The cache does not outlive its driver; its listener is then removed
     */
    public function testListenerOfAFreedCacheIsRemoved(): void
    {
        $listeners = $this->listenerCount();

        // Executing a schema is not needed; webonyx keeps the last one it
        // executed, and with it the driver
        $driver = new Driver(self::$entityManager, new Config(['useQueryResultCache' => true]));
        $driver->get(QueryResultCache::class);
        $this->assertSame($listeners + 1, $this->listenerCount());

        unset($driver);
        gc_collect_cycles();
        self::$entityManager->clear();

        $this->assertSame($listeners, $this->listenerCount());
    }

    public function testDisabledCacheRegistersNoListener(): void
    {
        $listeners = $this->listenerCount();

        $driver = new Driver(self::$entityManager, new Config(['useQueryResultCache' => false]));
        GraphQL::executeQuery($this->schema($driver), '{ artists { edges { node { id } } } }');

        $this->assertSame($listeners, $this->listenerCount());
    }

    /**
     * An entity parameter is keyed by its identifier rather than by its state
     */
    public function testEntityParametersAreKeyedByIdentifier(): void
    {
        $cache  = new QueryResultCache();
        $artist = self::$entityManager->getRepository(Artist::class)->findOneBy(['name' => 'Phish']);
        $this->assertInstanceOf(Artist::class, $artist);

        $dql = 'SELECT p FROM ' . Performance::class . ' p WHERE p.artist = :artist';

        $query = self::$entityManager->createQuery($dql)->setParameter('artist', $artist);
        $cache->set($query, $query->getResult());

        $artist->setName('Changed');

        $this->assertTrue($cache->has(self::$entityManager->createQuery($dql)->setParameter('artist', $artist)));
    }
}
