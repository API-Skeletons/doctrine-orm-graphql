<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Traits\ExcludeFilters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Traits\MagicGetter;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe an association for GraphQL
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Association
{
    use ExcludeFilters;
    use MagicGetter;

    /**
     * @param Filters[] $excludeFilters
     * @param Filters[] $includeFilters
     */
    public function __construct(
        private string $group = 'default',
        private string|null $alias = null,
        private string|null $description = null,
        private int|null $limit = null,
        private string|null $criteriaEventName = null,
        private string|null $hydratorStrategy = null,
        array $excludeFilters = [],
        array $includeFilters = [],
    ) {
        $this->excludeFilters = $excludeFilters;
        $this->includeFilters = $includeFilters;
    }
}
