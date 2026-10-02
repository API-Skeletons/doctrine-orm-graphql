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
 * An entity whose identifier is stored in another form than PHP holds it,
 * as a binary UUID is
 */
#[GraphQL\Entity(group: 'TypedId')]
#[ORM\Entity]
class TypedIdAuthor
{
    /** @var Collection<int, TypedIdBook> */
    #[GraphQL\Association(group: 'TypedId')]
    #[ORM\OneToMany(targetEntity: TypedIdBook::class, mappedBy: 'author')]
    private Collection $books;

    public function __construct(
        #[GraphQL\Field(group: 'TypedId', type: 'string', hydratorStrategy: ToString::class)]
        #[ORM\Id]
        #[ORM\Column(type: CodeType::NAME)]
        private Code $id,
        #[GraphQL\Field(group: 'TypedId')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): Code
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, TypedIdBook> */
    public function getBooks(): Collection
    {
        return $this->books;
    }
}
