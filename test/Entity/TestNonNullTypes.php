<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a field or association of each nullability, for the
 * useNonNullTypes config option
 */
#[GraphQL\Entity(group: 'NonNullTypes')]
#[ORM\Entity]
class TestNonNullTypes
{
    #[GraphQL\Field(group: 'NonNullTypes')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'NonNullTypes')]
    #[ORM\Column(type: 'string')]
    private string $name = 'required';

    #[GraphQL\Field(group: 'NonNullTypes')]
    #[ORM\Column(type: 'string', nullable: true)]
    private string|null $nickname = null;

    /** Of a custom type which is already non-null */
    #[GraphQL\Field(group: 'NonNullTypes', type: 'requiredstring')]
    #[ORM\Column(type: 'string')]
    private string $code = 'code';

    #[GraphQL\Association(group: 'NonNullTypes')]
    #[ORM\ManyToOne(targetEntity: Artist::class)]
    #[ORM\JoinColumn(name: 'required_artist_id', referencedColumnName: 'id', nullable: false)]
    private Artist $requiredArtist;

    #[GraphQL\Association(group: 'NonNullTypes')]
    #[ORM\ManyToOne(targetEntity: Artist::class)]
    #[ORM\JoinColumn(name: 'optional_artist_id', referencedColumnName: 'id', nullable: true)]
    private Artist|null $optionalArtist = null;

    /** A join column is nullable by default */
    #[GraphQL\Association(group: 'NonNullTypes')]
    #[ORM\ManyToOne(targetEntity: Artist::class)]
    private Artist|null $defaultArtist = null;

    /** The inverse side, which has no join column */
    #[GraphQL\Association(group: 'NonNullTypes')]
    #[ORM\OneToOne(targetEntity: TestNonNullTypesDetail::class, mappedBy: 'owner')]
    private TestNonNullTypesDetail|null $detail = null;

    public function __construct(Artist $requiredArtist)
    {
        $this->requiredArtist = $requiredArtist;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNickname(): string|null
    {
        return $this->nickname;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getRequiredArtist(): Artist
    {
        return $this->requiredArtist;
    }

    public function getOptionalArtist(): Artist|null
    {
        return $this->optionalArtist;
    }

    public function getDefaultArtist(): Artist|null
    {
        return $this->defaultArtist;
    }

    public function getDetail(): TestNonNullTypesDetail|null
    {
        return $this->detail;
    }

    #[GraphQL\ComputedField(type: 'string', group: 'NonNullTypes')]
    public function getLabel(): string
    {
        return $this->name;
    }
}
