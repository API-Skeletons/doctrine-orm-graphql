<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ArrayObject;
use League\Event\HasEventName;

/**
 * This event is fired when the metadta is created
 */
class Metadata implements
    HasEventName
{
    public function __construct(
        protected ArrayObject $metadata,
        protected string $eventName,
    ) {
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function getMetadata(): ArrayObject
    {
        return $this->metadata;
    }
}
