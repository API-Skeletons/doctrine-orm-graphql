<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ArrayObject;
use League\Event\HasEventName;

class EntityDefinition implements
    HasEventName
{
    public function __construct(
        protected ArrayObject $definition,
        protected string $eventName,
    ) {
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function getDefinition(): ArrayObject
    {
        return $this->definition;
    }
}
