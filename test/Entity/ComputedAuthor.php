<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use function array_values;

/**
 * Computed fields of entity types: an author's books, and the author
 * itself.  Its books are not exposed as an association.
 */
#[GraphQL\Entity(group: 'ComputedEntity', description: 'An author')]
#[ORM\Entity]
class ComputedAuthor
{
    #[GraphQL\Field(group: 'ComputedEntity')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, ComputedBook> */
    #[ORM\OneToMany(targetEntity: ComputedBook::class, mappedBy: 'author')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $books;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedEntity')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, ComputedBook> */
    #[GraphQL\ComputedField(type: ComputedBook::class, group: 'ComputedEntity', list: true)]
    public function getBookList(): Collection
    {
        return $this->books;
    }

    /** @return list<string> */
    #[GraphQL\ComputedField(type: 'string', group: 'ComputedEntity', list: true)]
    public function getBookTitles(): array
    {
        $titles = [];
        foreach ($this->books as $book) {
            $titles[] = $book->getTitle();
        }

        return array_values($titles);
    }

    #[GraphQL\ComputedField(type: self::class, group: 'ComputedEntity', description: 'The author itself')]
    public function getSelf(): self
    {
        return $this;
    }
}
