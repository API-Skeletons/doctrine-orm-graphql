<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member of the ordered collections of TestOrderedOwner
 */
#[GraphQL\Entity(group: 'OrderBy')]
#[GraphQL\Entity(group: 'OrderByEvent')]
#[ORM\Entity]
class TestOrderedItem
{
    #[GraphQL\Field(group: 'OrderBy')]
    #[GraphQL\Field(group: 'OrderByEvent')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'OrderBy')]
        #[GraphQL\Field(group: 'OrderByEvent')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[GraphQL\Field(group: 'OrderBy')]
        #[GraphQL\Field(group: 'OrderByEvent')]
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\ManyToOne(targetEntity: TestOrderedOwner::class, inversedBy: 'items')]
        #[ORM\JoinColumn(nullable: false)]
        private TestOrderedOwner $owner,
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

    public function getPosition(): int
    {
        return $this->position;
    }
}
