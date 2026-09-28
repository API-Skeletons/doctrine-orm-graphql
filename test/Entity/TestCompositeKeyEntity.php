<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a composite identifier
 */
#[GraphQL\Entity(group: 'CompositeKeyTest')]
#[ORM\Entity]
class TestCompositeKeyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $firstId,
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $secondId,
        #[GraphQL\Field(group: 'CompositeKeyTest')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\ManyToOne(targetEntity: Artist::class, inversedBy: 'compositeKeyEntities')]
        #[ORM\JoinColumn(name: 'artist_id', referencedColumnName: 'id', nullable: true)]
        private Artist|null $artist = null,
    ) {
    }

    public function getFirstId(): int
    {
        return $this->firstId;
    }

    public function getSecondId(): int
    {
        return $this->secondId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArtist(): Artist|null
    {
        return $this->artist;
    }
}
