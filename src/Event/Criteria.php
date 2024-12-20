<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria as DoctrineCriteria;
use Doctrine\ORM\PersistentCollection;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\HasEventName;

/**
 * This event is dispatched when a Doctrine Criteria is created.
 * Define an event using the Association::$criteriaEventName
 */
class Criteria implements
    HasEventName
{
    /**
     * @param PersistentCollection<array-key, mixed> $collection
     * @param mixed[]                                $args
     */
    public function __construct(
        protected readonly string $eventName,
        protected readonly DoctrineCriteria $criteria,
        protected readonly Collection $collection,
        protected readonly mixed $objectValue,
        protected readonly array $args,
        protected readonly mixed $context,
        protected readonly ResolveInfo $info,
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

    /** @return PersistentCollection<array-key, mixed> */
    public function getCollection(): Collection
    {
        return $this->collection;
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
