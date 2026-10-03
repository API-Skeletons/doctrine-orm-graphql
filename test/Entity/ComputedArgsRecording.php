<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A recording of a ComputedArgsArtist, in a year
 */
#[GraphQL\Entity(group: 'ComputedArgs')]
#[ORM\Entity]
class ComputedArgsRecording
{
    #[GraphQL\Field(group: 'ComputedArgs')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedArgs')]
        #[ORM\Column(type: 'string')]
        private string $title,
        #[GraphQL\Field(group: 'ComputedArgs')]
        #[ORM\Column(type: 'integer')]
        private int $year,
        #[ORM\ManyToOne(targetEntity: ComputedArgsArtist::class, inversedBy: 'recordings')]
        private ComputedArgsArtist $artist,
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

    public function getYear(): int
    {
        return $this->year;
    }

    public function getArtist(): ComputedArgsArtist
    {
        return $this->artist;
    }
}
