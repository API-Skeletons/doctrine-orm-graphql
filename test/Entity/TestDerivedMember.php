<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity of a derived identity: its identifier is an association
 */
#[GraphQL\Entity(group: 'DerivedIdentity')]
#[ORM\Entity]
class TestDerivedMember
{
    /** @var Collection<int, TestDerivedBadge> */
    #[GraphQL\Association(group: 'DerivedIdentity')]
    #[ORM\OneToMany(targetEntity: TestDerivedBadge::class, mappedBy: 'member')]
    private Collection $badges;

    public function __construct(
        #[GraphQL\Association(group: 'DerivedIdentity')]
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: TestDerivedAccount::class)]
        private TestDerivedAccount $account,
        #[ORM\ManyToOne(targetEntity: TestDerivedTeam::class, inversedBy: 'members')]
        #[ORM\JoinColumn(nullable: false)]
        private TestDerivedTeam $team,
        #[GraphQL\Field(group: 'DerivedIdentity')]
        #[ORM\Column(type: 'string')]
        private string $role,
    ) {
        $this->badges = new ArrayCollection();
    }

    public function getAccount(): TestDerivedAccount
    {
        return $this->account;
    }

    public function getTeam(): TestDerivedTeam
    {
        return $this->team;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /** @return Collection<int, TestDerivedBadge> */
    public function getBadges(): Collection
    {
        return $this->badges;
    }
}
