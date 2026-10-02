<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\DbalType\Code;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\DbalType\CodeType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity whose identifier, and its author's, are stored in another form
 * than PHP holds them, as binary UUIDs are
 */
#[GraphQL\Entity(group: 'TypedId')]
#[ORM\Entity]
class TypedIdBook
{
    /** @var Collection<int, TypedIdTag> */
    #[GraphQL\Association(group: 'TypedId')]
    #[ORM\ManyToMany(targetEntity: TypedIdTag::class, inversedBy: 'books')]
    #[ORM\JoinTable(name: 'typed_id_book_tag')]
    #[ORM\JoinColumn(referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(referencedColumnName: 'id')]
    private Collection $tags;

    public function __construct(
        #[GraphQL\Field(group: 'TypedId', type: 'string', hydratorStrategy: ToString::class)]
        #[ORM\Id]
        #[ORM\Column(type: CodeType::NAME)]
        private Code $id,
        #[GraphQL\Field(group: 'TypedId')]
        #[ORM\Column(type: 'string')]
        private string $title,
        #[GraphQL\Association(group: 'TypedId')]
        #[ORM\ManyToOne(targetEntity: TypedIdAuthor::class, inversedBy: 'books')]
        private TypedIdAuthor $author,
    ) {
        $this->tags = new ArrayCollection();
    }

    public function getId(): Code
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): TypedIdAuthor
    {
        return $this->author;
    }

    /** @return Collection<int, TypedIdTag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TypedIdTag $tag): void
    {
        $this->tags->add($tag);
        $tag->getBooks()->add($this);
    }
}
