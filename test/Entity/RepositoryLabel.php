<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A label, whose artists are a batched collection
 */
#[GraphQL\Entity(group: 'RepositoryComputed')]
#[ORM\Entity]
class RepositoryLabel
{
    #[GraphQL\Field(group: 'RepositoryComputed')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, RepositoryArtist> */
    #[GraphQL\Association(group: 'RepositoryComputed')]
    #[ORM\OneToMany(targetEntity: RepositoryArtist::class, mappedBy: 'label')]
    private Collection $artists;

    public function __construct(
        #[GraphQL\Field(group: 'RepositoryComputed')]
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

    /** @return Collection<int, RepositoryArtist> */
    public function getArtists(): Collection
    {
        return $this->artists;
    }
}
