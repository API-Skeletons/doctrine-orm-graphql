<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * The owning side of a one-to-one association whose join column is not
 * nullable
 */
#[GraphQL\Entity(group: 'NonNullTypes')]
#[ORM\Entity]
class TestNonNullTypesDetail
{
    #[GraphQL\Field(group: 'NonNullTypes')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Association(group: 'NonNullTypes')]
    #[ORM\OneToOne(targetEntity: TestNonNullTypes::class, inversedBy: 'detail')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    private TestNonNullTypes $owner;

    public function __construct(TestNonNullTypes $owner)
    {
        $this->owner = $owner;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): TestNonNullTypes
    {
        return $this->owner;
    }
}
