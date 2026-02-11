<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

use function strtoupper;

/**
 * Test entity where computed field name collides with existing field
 */
#[GraphQL\Entity(group: 'collisionTest')]
#[ORM\Entity]
class TestEntityWithCollision
{
    #[GraphQL\Field(group: 'collisionTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'collisionTest')]
    #[ORM\Column(type: 'string')]
    private string $name;

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * This computed field should cause a collision with the 'name' field above
     */
    #[GraphQL\ComputedField(type: 'string', group: 'collisionTest')]
    public function getName(): string
    {
        return strtoupper($this->name);
    }
}
