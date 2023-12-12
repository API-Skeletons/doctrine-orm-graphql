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

    /** @var string The GraphQL group */
    private string $group;

    /** @var bool Extract by value: true, or by reference: false */
    private bool $byValue;

    /**
     * When this value is 0 the limit falls back to the global config limit
     *
     * @var int A hard limit for all queries on this entity
     */
    private int $limit;

    /** @var string|null Documentation for the entity within GraphQL */
    private string|null $description = null;

    /**
     * @var mixed[] An array of filters as
     * [
     *   'condition' => FilterComposite::CONDITION_AND,
     *   'filter' => 'Filter\ClassName',
     * ]
     */
    private array $hydratorFilters = [];

    /**
     * @param array<array<string, string>> $hydratorFilters
     * @param Filters[]                    $excludeFilters
     * @param Filters[]                    $includeFilters
     */
    public function __construct(
        string $group = 'default',
        bool $byValue = true,
        int $limit = 0,
        string|null $description = null,
        private string|null $typeName = null,
        array $hydratorFilters = [],
        private string|null $hydratorNamingStrategy = null,
        array $excludeFilters = [],
        array $includeFilters = [],
    ) {
        $this->group           = $group;
        $this->byValue         = $byValue;
        $this->limit           = $limit;
        $this->description     = $description;
        $this->hydratorFilters = $hydratorFilters;
        $this->includeFilters  = $includeFilters;
        $this->excludeFilters  = $excludeFilters;
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

    /** @return mixed[] */
    public function getHydratorFilters(): array
    {
        return $this->hydratorFilters;
    }

    public function getHydratorNamingStrategy(): string|null
    {
        return $this->hydratorNamingStrategy;
    }
}
