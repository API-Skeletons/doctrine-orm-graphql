<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe an association for GraphQL
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Association
{
    use ExcludeFilters;

    /**
     * @param Filters[] $excludeFilters
     * @param Filters[] $includeFilters
     */
    public function __construct(
        private readonly string $group = 'default',
        private readonly string|null $alias = null,
        private readonly string|null $description = null,
        private readonly int|null $limit = null,
        private readonly string|null $criteriaEventName = null,
        private readonly string|null $hydratorStrategy = null,
        private readonly array $excludeFilters = [],
        private readonly array $includeFilters = [],
    ) {
    }

    public function getAlias(): string|null
    {
        return $this->alias;
    }

    public function getLimit(): int|null
    {
        return $this->limit;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getHydratorStrategy(): string|null
    {
        return $this->hydratorStrategy;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getCriteriaEventName(): string|null
    {
        return $this->criteriaEventName;
    }
}
