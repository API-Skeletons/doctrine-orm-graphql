<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe a field for GraphQL
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Field
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
        protected string|null $type = null,
        private array $excludeFilters = [],
        private array $includeFilters = [],
    ) {
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

    public function getType(): string|null
    {
        return $this->type;
    }
}
