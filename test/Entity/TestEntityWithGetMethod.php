<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test entity with getXxx() method
 */
#[GraphQL\Entity(group: 'getMethodTest')]
#[ORM\Entity]
class TestEntityWithGetMethod
{
    #[GraphQL\Field(group: 'getMethodTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'getMethodTest')]
    #[ORM\Column(type: 'string')]
    private string $value;

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * getXxx() method should have prefix removed
     */
    #[GraphQL\ComputedField(type: 'string', group: 'getMethodTest')]
    public function getDisplayValue(): string
    {
        return 'Display: ' . $this->value;
    }
}
