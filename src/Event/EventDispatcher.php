<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use League\Event\Emitter as EventDispatcherClass;

/**
 * This class is a wrapper for the League\Event\Emitter class.
 * Because the version of League\Event was changed from 3.0 to 2.2,
 * this class was created to keep the code consistent and move easily
 * move back to 3.0 when the time comes.
 */
class EventDispatcher
{
    public function __construct(
        protected EventDispatcherClass $eventDispatcher,
    ) {
    }

    public function dispatch(string $event, mixed $payload = null): void
    {
        $this->eventDispatcher->emit($event, $payload);
    }

    public function subscribeTo(string $event, callable $listener): void
    {
        $this->eventDispatcher->addListener($event, $listener);
    }
}
