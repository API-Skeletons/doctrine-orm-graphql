<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test entity with method that doesn't start with get/is
 */
#[GraphQL\Entity(group: 'plainMethodTest')]
#[ORM\Entity]
class TestEntityWithPlainMethod
{
    #[GraphQL\Field(group: 'plainMethodTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'plainMethodTest')]
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
     * Method without get/is prefix - should use method name as-is
     */
    #[GraphQL\ComputedField(type: 'string', group: 'plainMethodTest')]
    public function calculate(): string
    {
        return 'calculated: ' . $this->value;
    }
}
