<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A record label, whose artists are filtered and sorted by their computed fields
 */
#[GraphQL\Entity(group: 'ComputedExpression')]
#[ORM\Entity]
class ComputedExpressionLabel
{
    #[GraphQL\Field(group: 'ComputedExpression')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, ComputedExpressionArtist> */
    #[GraphQL\Association(group: 'ComputedExpression', excludeFilters: [Filters::ISNULL])]
    #[ORM\OneToMany(targetEntity: ComputedExpressionArtist::class, mappedBy: 'label')]
    private Collection $artists;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedExpression')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->artists = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, ComputedExpressionArtist> */
    public function getArtists(): Collection
    {
        return $this->artists;
    }
}
