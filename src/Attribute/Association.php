<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\GraphQL\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Association
{
    use ExcludeCriteria;

    /**
     * @param string[] $excludeCriteria
     * @param string[] $includeCriteria
     */
    public function __construct(
        protected string $group = 'default',
        protected string|null $strategy = null,
        protected string|null $description = null,
        protected array $excludeCriteria = [],
        protected array $includeCriteria = [],
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
