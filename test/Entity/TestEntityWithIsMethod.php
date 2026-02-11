<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test entity with isXxx() method
 */
#[GraphQL\Entity(group: 'isMethodTest2')]
#[ORM\Entity]
class TestEntityWithIsMethod
{
    #[GraphQL\Field(group: 'isMethodTest2')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'isMethodTest2')]
    #[ORM\Column(type: 'boolean')]
    private bool $valid;

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    /**
     * isXxx() method should keep its name
     */
    #[GraphQL\ComputedField(type: 'boolean', group: 'isMethodTest2')]
    public function isValid(): bool
    {
        return $this->valid;
    }
}
