<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with collections ordered by #[ORM\OrderBy]
 */
#[GraphQL\Entity(group: 'OrderBy')]
#[GraphQL\Entity(group: 'OrderByEvent')]
#[ORM\Entity]
class TestOrderedOwner
{
    #[GraphQL\Field(group: 'OrderBy')]
    #[GraphQL\Field(group: 'OrderByEvent')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, TestOrderedItem> */
    #[GraphQL\Association(group: 'OrderBy')]
    #[GraphQL\Association(group: 'OrderByEvent', eventName: 'orderedItems')]
    #[ORM\OneToMany(targetEntity: TestOrderedItem::class, mappedBy: 'owner')]
    #[ORM\OrderBy(['name' => 'DESC'])]
    private Collection $items;

    /** @var Collection<int, TestOrderedItem> */
    #[GraphQL\Association(group: 'OrderBy')]
    #[GraphQL\Association(group: 'OrderByEvent', eventName: 'orderedTags')]
    #[ORM\ManyToMany(targetEntity: TestOrderedItem::class)]
    #[ORM\JoinTable(name: 'test_ordered_owner_tag')]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $tags;

    public function __construct(
        #[GraphQL\Field(group: 'OrderBy')]
        #[GraphQL\Field(group: 'OrderByEvent')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->items = new ArrayCollection();
        $this->tags  = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, TestOrderedItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @return Collection<int, TestOrderedItem> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TestOrderedItem $item): self
    {
        $this->tags->add($item);

        return $this;
    }
}
