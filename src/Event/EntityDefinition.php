<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits\MagicGetter;
use ArrayObject;
use League\Event\HasEventName;

/**
 * This event is fired each time an entity GraphQL type is created
 */
readonly class EntityDefinition implements
    HasEventName
{
    use MagicGetter;

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
}
