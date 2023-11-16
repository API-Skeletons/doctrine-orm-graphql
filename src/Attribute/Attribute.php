<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

abstract class Attribute
{
    /** @var string The GraphQL group */
    protected string $group;

    /** @var string|null Documentation for the entity within GraphQL */
    protected string|null $description = null;

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }
}
