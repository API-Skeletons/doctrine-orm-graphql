<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A recording of a RepositoryArtist, released on a date
 */
#[GraphQL\Entity(group: 'RepositoryComputed')]
#[ORM\Entity]
class RepositoryRecording
{
    #[GraphQL\Field(group: 'RepositoryComputed')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'RepositoryComputed')]
        #[ORM\Column(type: 'string')]
        private string $title,
        #[GraphQL\Field(group: 'RepositoryComputed')]
        #[ORM\Column(type: 'date_immutable')]
        private DateTimeImmutable $released,
        #[ORM\ManyToOne(targetEntity: RepositoryArtist::class, inversedBy: 'recordings')]
        private RepositoryArtist $artist,
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

    public function getReleased(): DateTimeImmutable
    {
        return $this->released;
    }

    public function getArtist(): RepositoryArtist
    {
        return $this->artist;
    }
}
