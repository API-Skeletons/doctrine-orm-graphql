<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits\MagicGetter;
use Doctrine\Common\Collections\Criteria as DoctrineCriteria;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\HasEventName;

/**
 * This event is dispatched when a Doctrine Criteria is created.
 * Define an event using the Association::$criteriaEventName
 */
readonly class Criteria implements
    HasEventName
{
    use MagicGetter;

    /** @param mixed[] $args */
    public function __construct(
        protected DoctrineCriteria $criteria,
        protected string $eventName,
        protected mixed $objectValue,
        protected array $args,
        protected mixed $context,
        protected ResolveInfo $info,
    ) {
    }

    public function eventName(): string
    {
        return $this->eventName;
    }
}
