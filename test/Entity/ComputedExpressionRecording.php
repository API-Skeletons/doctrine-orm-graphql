<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A recording of a ComputedExpressionArtist, released on a date
 */
#[GraphQL\Entity(group: 'ComputedExpression')]
#[ORM\Entity]
class ComputedExpressionRecording
{
    #[GraphQL\Field(group: 'ComputedExpression')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedExpression')]
        #[ORM\Column(type: 'string')]
        private string $title,
        #[GraphQL\Field(group: 'ComputedExpression')]
        #[ORM\Column(type: 'date_immutable')]
        private DateTimeImmutable $released,
        #[ORM\ManyToOne(targetEntity: ComputedExpressionArtist::class, inversedBy: 'recordings')]
        private ComputedExpressionArtist $artist,
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

    public function getYear(): int
    {
        return (int) $this->released->format('Y');
    }

    public function getArtist(): ComputedExpressionArtist
    {
        return $this->artist;
    }
}
