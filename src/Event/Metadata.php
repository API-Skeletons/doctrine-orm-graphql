<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits\MagicGetter;
use ArrayObject;
use League\Event\HasEventName;

/**
 * This event is fired when the metadta is created
 */
readonly class Metadata implements
    HasEventName
{
    use MagicGetter;

    public function __construct(
        protected ArrayObject $metadata,
        protected string $eventName,
    ) {
    }

    public function eventName(): string
    {
        return $this->eventName;
    }
}
