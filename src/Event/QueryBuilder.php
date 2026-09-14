<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\HasEventName;
use Override;

/**
 * This event is fired when the QueryBuilder is created for an entity
 *
 * The event is dispatched before the rows are counted so that a listener may
 * modify the QueryBuilder and have that modification reflected in both the
 * total count and the rows returned.  The offset and limit it carries are
 * therefore the window the client requested, not the window finally queried.
 */
final class QueryBuilder implements
    HasEventName
{
    /** @param mixed[] $args */
    public function __construct(
        protected readonly string $eventName,
        protected readonly DoctrineQueryBuilder $queryBuilder,
        protected readonly int $requestedOffset,
        protected readonly int $requestedLimit,
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

    /**
     * The offset the client asked for
     *
     * A backward request - `last` without a `before` cursor - reports zero
     * because its offset depends on a row count which has not been taken when
     * this event is dispatched.  Read `getArgs()['pagination']` to tell a
     * backward request from a request for the first page.
     */
    public function getRequestedOffset(): int
    {
        return $this->requestedOffset;
    }

    /**
     * The page size the client asked for, capped by the configured limit
     */
    public function getRequestedLimit(): int
    {
        return $this->requestedLimit;
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
