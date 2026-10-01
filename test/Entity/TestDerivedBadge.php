<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a to-one association to an entity of a derived identity
 */
#[GraphQL\Entity(group: 'DerivedIdentity')]
#[ORM\Entity]
class TestDerivedBadge
{
    #[GraphQL\Field(group: 'DerivedIdentity')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'DerivedIdentity')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[GraphQL\Association(group: 'DerivedIdentity')]
        #[ORM\ManyToOne(targetEntity: TestDerivedMember::class, inversedBy: 'badges')]
        #[ORM\JoinColumn(referencedColumnName: 'account_id', nullable: false)]
        private TestDerivedMember $member,
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

    public function getMember(): TestDerivedMember
    {
        return $this->member;
    }
}
