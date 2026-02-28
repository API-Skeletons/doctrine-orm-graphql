<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\HasEventName;
use Override;

/**
 * This event is fired when the QueryBuilder is created for an entity
 */
final class QueryBuilder implements
    HasEventName
{
    /** @param mixed[] $args */
    public function __construct(
        protected readonly string $eventName,
        protected readonly DoctrineQueryBuilder $queryBuilder,
        protected readonly int $offset,
        protected readonly int $limit,
        protected readonly mixed $objectValue,
        protected readonly array $args,
        protected readonly mixed $context,
        protected readonly ResolveInfo $info,
    ) {
    }

    #[Override]
    public function eventName(): string
    {
        return $this->eventName;
    }

    public function getQueryBuilder(): DoctrineQueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
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
