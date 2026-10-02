<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Associations to an entity which is not exposed in the group: a collection
 * in the UnexposedCollection group and a to-one in the UnexposedToOne group
 */
#[GraphQL\Entity(group: 'UnexposedCollection')]
#[GraphQL\Entity(group: 'UnexposedToOne')]
#[ORM\Entity]
class UnexposedTargetOwner
{
    #[GraphQL\Field(group: 'UnexposedCollection')]
    #[GraphQL\Field(group: 'UnexposedToOne')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, UnexposedTargetItem> */
    #[GraphQL\Association(group: 'UnexposedCollection')]
    #[ORM\OneToMany(targetEntity: UnexposedTargetItem::class, mappedBy: 'owner')]
    private Collection $items;

    #[GraphQL\Association(group: 'UnexposedToOne')]
    #[ORM\ManyToOne(targetEntity: UnexposedTargetItem::class)]
    private UnexposedTargetItem|null $favourite = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @return Collection<int, UnexposedTargetItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getFavourite(): UnexposedTargetItem|null
    {
        return $this->favourite;
    }
}
