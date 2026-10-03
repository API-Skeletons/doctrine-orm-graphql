<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * The root of a single table inheritance hierarchy.  Its subclass is exposed
 * in the InheritanceSubclass group only.
 */
#[GraphQL\Entity(group: 'Inheritance')]
#[GraphQL\Entity(group: 'InheritanceSubclass')]
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['animal' => InheritanceAnimal::class, 'dog' => InheritanceDog::class])]
class InheritanceAnimal
{
    #[GraphQL\Field(group: 'Inheritance')]
    #[GraphQL\Field(group: 'InheritanceSubclass')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, InheritanceToy> */
    #[GraphQL\Association(group: 'Inheritance')]
    #[GraphQL\Association(group: 'InheritanceSubclass')]
    #[ORM\OneToMany(targetEntity: InheritanceToy::class, mappedBy: 'owner')]
    private Collection $toys;

    public function __construct(
        #[GraphQL\Field(group: 'Inheritance')]
        #[GraphQL\Field(group: 'InheritanceSubclass')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->toys = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, InheritanceToy> */
    public function getToys(): Collection
    {
        return $this->toys;
    }

    /**
     * A subclass's own fields, through a field of the root's type: the
     * animal as a dog, or null
     */
    #[GraphQL\ComputedField(type: InheritanceDog::class, group: 'InheritanceSubclass')]
    public function getAsDog(): InheritanceDog|null
    {
        return $this instanceof InheritanceDog ? $this : null;
    }
}
