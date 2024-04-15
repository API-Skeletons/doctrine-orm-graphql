<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ArrayObject;

/**
 * This event is fired each time an entity GraphQL type is created
 */
class EntityDefinition
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
