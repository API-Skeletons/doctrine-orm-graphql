<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe an association for GraphQL
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Association
{
    use ExcludeFilters;

    /**
     * @param Filters[] $excludeFilters
     * @param Filters[] $includeFilters
     */
    public function __construct(
        protected string $group = 'default',
        protected string|null $strategy = null,
        protected string|null $description = null,
        protected array $excludeFilters = [],
        protected array $includeFilters = [],
        protected string|null $filterCriteriaEventName = null,
        protected int|null $limit = null,
    ) {
    }

    public function getLimit(): int|null
    {
        return $this->limit;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getStrategy(): string|null
    {
        return $this->strategy;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getFilterCriteriaEventName(): string|null
    {
        return $this->filterCriteriaEventName;
    }
}
