<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use Doctrine\Common\Collections\Criteria as DoctrineCriteria;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * This event is dispatched when a Doctrine Criteria is created.
 * Define an event using the Association::$criteriaEventName
 */
class Criteria
{
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

    public function getCriteria(): DoctrineCriteria
    {
        return $this->criteria;
    }

    public function getObjectValue(): mixed
    {
        return $this->objectValue;
    }

    /** @return mixed[] */
    public function getArgs(): array
    {
        return $this->args;
    }

    public function getContext(): mixed
    {
        return $this->context;
    }

    public function getInfo(): ResolveInfo
    {
        return $this->info;
    }
}
