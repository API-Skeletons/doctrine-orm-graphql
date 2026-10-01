<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a collection of entities of a derived identity
 */
#[GraphQL\Entity(group: 'DerivedIdentity')]
#[ORM\Entity]
class TestDerivedTeam
{
    #[GraphQL\Field(group: 'DerivedIdentity')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, TestDerivedMember> */
    #[GraphQL\Association(group: 'DerivedIdentity')]
    #[ORM\OneToMany(targetEntity: TestDerivedMember::class, mappedBy: 'team')]
    private Collection $members;

    public function __construct(
        #[GraphQL\Field(group: 'DerivedIdentity')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->members = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, TestDerivedMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }
}
