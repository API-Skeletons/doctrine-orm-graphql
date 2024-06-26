<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to define an entity for GraphQL
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Entity
{
    use ExcludeFilters;

    /**
     * @param Filters[] $excludeFilters
     * @param Filters[] $includeFilters
     */
    public function __construct(
        protected string $group = 'default',
        protected bool $byValue = true,
        protected int $limit = 0,
        protected string|null $description = null,
        private string|null $typeName = null,
        array $excludeFilters = [],
        array $includeFilters = [],
    ) {
        $this->includeFilters = $includeFilters;
        $this->excludeFilters = $excludeFilters;
    }

    public function getGroup(): string|null
    {
        return $this->group;
    }

    public function getByValue(): bool
    {
        return $this->byValue;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getTypeName(): string|null
    {
        return $this->typeName;
    }
}
