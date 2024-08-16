<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Traits\ExcludeFilters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Traits\MagicGetter;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to define an entity for GraphQL
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Entity
{
    use ExcludeFilters;
    use MagicGetter;

    /**
     * @param Filters[] $excludeFilters
     * @param Filters[] $includeFilters
     */
    public function __construct(
        private string $group = 'default',
        private bool $byValue = true,
        private int $limit = 0,
        private string|null $description = null,
        private string|null $typeName = null,
        array $excludeFilters = [],
        array $includeFilters = [],
    ) {
        $this->excludeFilters = $excludeFilters;
        $this->includeFilters = $includeFilters;
    }
}
