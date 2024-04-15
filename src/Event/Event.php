<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use League\Event\AbstractEvent;
use League\Event\EventInterface;

/**
 * This exists because league/event 3.0 is not supported by league/oauth2-server
 */
abstract class Event extends AbstractEvent implements
    EventInterface
{
    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }
}
