<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ArrayObject;
use League\Event\HasEventName;

class EntityDefinition implements
    HasEventName
{
    /** @param ArrayObject $definition<'description'|'fields'|'name'|'resolveField', mixed> */
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
