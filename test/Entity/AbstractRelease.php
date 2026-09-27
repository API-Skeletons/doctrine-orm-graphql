<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A mapped superclass whose mapped properties are private.  Reflection on a
 * child class cannot see a private property declared here.
 */
#[ORM\MappedSuperclass]
abstract class AbstractRelease
{
    #[GraphQL\Field(group: 'MappedSuperclassTest')]
    #[GraphQL\Field(group: 'MappedSuperclassByReferenceTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[GraphQL\Field(group: 'MappedSuperclassTest')]
    #[GraphQL\Field(group: 'MappedSuperclassByReferenceTest')]
    #[ORM\Column(type: 'string', nullable: false)]
    private string $title;

    #[GraphQL\Association(group: 'MappedSuperclassTest')]
    #[GraphQL\Association(group: 'MappedSuperclassByReferenceTest')]
    #[ORM\ManyToOne(targetEntity: Artist::class)]
    #[ORM\JoinColumn(name: 'artist_id', referencedColumnName: 'id', nullable: false)]
    private Artist $artist;

    public function getId(): int
    {
        return $this->id;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setArtist(Artist $artist): self
    {
        $this->artist = $artist;

        return $this;
    }

    public function getArtist(): Artist
    {
        return $this->artist;
    }
}
