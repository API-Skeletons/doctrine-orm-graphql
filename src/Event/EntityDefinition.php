<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Definition;
use League\Event\HasEventName;
use Override;

/**
 * This event is fired each time an entity GraphQL type is created
 */
final class EntityDefinition implements
    HasEventName
{
    public function __construct(
        protected readonly Definition $definition,
        protected readonly string $eventName,
    ) {
    }

    #[Override]
    public function eventName(): string
    {
        return $this->eventName;
    }

    public function getDefinition(): Definition
    {
        return $this->definition;
    }
}
