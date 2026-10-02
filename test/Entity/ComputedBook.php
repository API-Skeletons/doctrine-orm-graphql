<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A computed field of an entity type: a book's author, which is not exposed
 * as an association.  In the ComputedEntityUnexposed group the author is not
 * exposed at all.
 */
#[GraphQL\Entity(group: 'ComputedEntity')]
#[GraphQL\Entity(group: 'ComputedEntityUnexposed')]
#[ORM\Entity]
class ComputedBook
{
    #[GraphQL\Field(group: 'ComputedEntity')]
    #[GraphQL\Field(group: 'ComputedEntityUnexposed')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedEntity')]
        #[GraphQL\Field(group: 'ComputedEntityUnexposed')]
        #[ORM\Column(type: 'string')]
        private string $title,
        #[ORM\ManyToOne(targetEntity: ComputedAuthor::class, inversedBy: 'books')]
        private ComputedAuthor $author,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    #[GraphQL\ComputedField(type: ComputedAuthor::class, group: 'ComputedEntity')]
    #[GraphQL\ComputedField(type: ComputedAuthor::class, group: 'ComputedEntityUnexposed')]
    public function getWriter(): ComputedAuthor
    {
        return $this->author;
    }

    /**
     * A list of an entity which is not loaded until its fields are read
     *
     * @return list<ComputedAuthor>
     */
    #[GraphQL\ComputedField(type: ComputedAuthor::class, group: 'ComputedEntity', list: true)]
    public function getWriters(): array
    {
        return [$this->author];
    }

    #[GraphQL\ComputedField(type: ComputedAuthor::class, group: 'ComputedEntity')]
    public function getEditor(): ComputedAuthor|null
    {
        return null;
    }
}
