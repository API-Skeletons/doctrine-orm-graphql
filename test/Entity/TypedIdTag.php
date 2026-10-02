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
 * The inverse side of a many-to-many between entities whose identifiers are
 * stored in another form than PHP holds them
 */
#[GraphQL\Entity(group: 'TypedId')]
#[ORM\Entity]
class TypedIdTag
{
    /** @var Collection<int, TypedIdBook> */
    #[GraphQL\Association(group: 'TypedId')]
    #[ORM\ManyToMany(targetEntity: TypedIdBook::class, mappedBy: 'tags')]
    private Collection $books;

    public function __construct(
        #[GraphQL\Field(group: 'TypedId', type: 'string', hydratorStrategy: ToString::class)]
        #[ORM\Id]
        #[ORM\Column(type: CodeType::NAME)]
        private Code $id,
    ) {
        $this->books = new ArrayCollection();
    }

    public function getId(): Code
    {
        return $this->id;
    }

    /** @return Collection<int, TypedIdBook> */
    public function getBooks(): Collection
    {
        return $this->books;
    }
}
