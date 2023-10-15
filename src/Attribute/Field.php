<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Field
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
        protected string|null $type = null,
        private array $excludeCriteria = [],
        private array $includeCriteria = [],
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
