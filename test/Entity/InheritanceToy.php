<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity associated with the root of an inheritance hierarchy
 */
#[GraphQL\Entity(group: 'Inheritance')]
#[GraphQL\Entity(group: 'InheritanceSubclass')]
#[ORM\Entity]
class InheritanceToy
{
    #[GraphQL\Field(group: 'Inheritance')]
    #[GraphQL\Field(group: 'InheritanceSubclass')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'Inheritance')]
        #[GraphQL\Field(group: 'InheritanceSubclass')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[GraphQL\Association(group: 'Inheritance')]
        #[GraphQL\Association(group: 'InheritanceSubclass')]
        #[ORM\ManyToOne(targetEntity: InheritanceAnimal::class, inversedBy: 'toys')]
        #[ORM\JoinColumn(nullable: false)]
        private InheritanceAnimal $owner,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwner(): InheritanceAnimal
    {
        return $this->owner;
    }
}
