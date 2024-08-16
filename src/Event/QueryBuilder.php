<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits\MagicGetter;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use League\Event\HasEventName;

/**
 * This event is fired when the QueryBuilder is created for an entity
 */
readonly class QueryBuilder implements
    HasEventName
{
    use MagicGetter;

    /** @param mixed[] $args */
    public function __construct(
        protected DoctrineQueryBuilder $queryBuilder,
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
