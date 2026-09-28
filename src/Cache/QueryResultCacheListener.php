<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Cache;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use WeakReference;

/**
 * Clears a query result cache when its entity manager is cleared or flushed.
 *
 * The entity manager's event manager holds the listener, not the cache, so a
 * cache is freed with its driver.  The listener then removes itself the next
 * time an event occurs.
 *
 * @internal
 */
final class QueryResultCacheListener
{
    /** @var WeakReference<QueryResultCache> */
    private WeakReference $cache;

    private function __construct(QueryResultCache $cache, private readonly EntityManagerInterface $entityManager)
    {
        $this->cache = WeakReference::create($cache);
    }

    public static function register(EntityManagerInterface $entityManager, QueryResultCache $cache): void
    {
        $entityManager->getEventManager()
            ->addEventListener([Events::onClear, Events::postFlush], new self($cache, $entityManager));
    }

    public function onClear(): void
    {
        $this->clear();
    }

    public function postFlush(): void
    {
        $this->clear();
    }

    private function clear(): void
    {
        $cache = $this->cache->get();

        if ($cache === null) {
            $this->entityManager->getEventManager()->removeEventListener([Events::onClear, Events::postFlush], $this);

            return;
        }

        $cache->clear();
    }
}
