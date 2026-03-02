<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata as MetadataObject;
use League\Event\HasEventName;
use Override;

/**
 * This event is fired when the metadta is created
 */
final class Metadata implements
    HasEventName
{
    public function __construct(
        protected readonly MetadataObject $metadata,
        protected readonly string $eventName,
    ) {
    }

    #[Override]
    public function eventName(): string
    {
        return $this->eventName;
    }

    public function getMetadata(): MetadataObject
    {
        return $this->metadata;
    }
}
