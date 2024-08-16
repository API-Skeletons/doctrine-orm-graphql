<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe a field for GraphQL
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class Field
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
        private readonly string|null $type = null,
        private readonly string|null $hydratorStrategy = null,
        array $excludeFilters = [],
        array $includeFilters = [],
    ) {
        $this->includeFilters = $includeFilters;
        $this->excludeFilters = $excludeFilters;
    }

    public function getAlias(): string|null
    {
        return $this->alias;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getHydratorStrategy(): string|null
    {
        return $this->hydratorStrategy;
    }

    public function getType(): string|null
    {
        return $this->type;
    }
}
