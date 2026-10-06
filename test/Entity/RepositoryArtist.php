<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryArtistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * An artist whose repository has computed fields
 */
#[GraphQL\Entity(group: 'RepositoryComputed')]
#[GraphQL\Entity(group: 'RepositoryNonNull')]
#[GraphQL\Entity(group: 'RepositoryInvalidReturn')]
#[GraphQL\Entity(group: 'RepositoryCollision')]
#[GraphQL\Entity(group: 'RepositoryFieldCollision')]
#[ORM\Entity(repositoryClass: RepositoryArtistRepository::class)]
class RepositoryArtist
{
    #[GraphQL\Field(group: 'RepositoryComputed')]
    #[GraphQL\Field(group: 'RepositoryNonNull')]
    #[GraphQL\Field(group: 'RepositoryInvalidReturn')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: RepositoryLabel::class, inversedBy: 'artists')]
    private RepositoryLabel|null $label = null;

    /** @var Collection<int, RepositoryRecording> */
    #[ORM\OneToMany(targetEntity: RepositoryRecording::class, mappedBy: 'artist')]
    private Collection $recordings;

    public function __construct(
        #[GraphQL\Field(group: 'RepositoryComputed')]
        #[GraphQL\Field(group: 'RepositoryFieldCollision')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
        $this->recordings = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): RepositoryLabel|null
    {
        return $this->label;
    }

    public function setLabel(RepositoryLabel $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @return Collection<int, RepositoryRecording> */
    public function getRecordings(): Collection
    {
        return $this->recordings;
    }

    /** Collides with the repository's computed field of the same name */
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryCollision')]
    public function getDisplayName(): string
    {
        return $this->name;
    }
}
