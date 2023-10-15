<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Entity
{
    use ExcludeCriteria;

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
    private array $filters = [];

    /**
     * @param mixed[]  $filters
     * @param string[] $excludeCriteria
     * @param string[] $includeCriteria
     */
    public function __construct(
        string $group = 'default',
        bool $byValue = true,
        int $limit = 0,
        string|null $description = null,
        private string|null $typeName = null,
        array $filters = [],
        private string|null $namingStrategy = null,
        private array $excludeCriteria = [],
        private array $includeCriteria = [],
    ) {
        $this->group       = $group;
        $this->byValue     = $byValue;
        $this->limit       = $limit;
        $this->description = $description;
        $this->filters     = $filters;
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
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getNamingStrategy(): string|null
    {
        return $this->namingStrategy;
    }
}
