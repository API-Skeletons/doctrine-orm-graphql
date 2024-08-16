<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Performance
 */
#[GraphQL\Entity(typeName: 'performance', description: 'Performances')]
#[GraphQL\Entity(group: 'ExcludeFiltersTest', excludeFilters: [Filters::CONTAINS])]
#[GraphQL\Entity(group: 'IncludeFiltersTest', includeFilters: [
    Filters::EQ,
    Filters::NEQ,
    Filters::CONTAINS,
])]
#[GraphQL\Entity(
    group: 'IncludeExcludeFiltersTest',
    excludeFilters: [Filters::IN],
    includeFilters: [
        Filters::EQ,
        Filters::NEQ,
        Filters::CONTAINS,
    ],
)]
#[GraphQL\Entity(group: 'CriteriaEvent')]
#[GraphQL\Entity(group: 'LimitTest')]
#[GraphQL\Entity(group: 'AttributeLimit')]
#[GraphQL\Entity(group: 'ExtractionMap')]

#[ORM\Entity]
class Performance
{
    #[GraphQL\Field(description: 'Venue name')]
    #[GraphQL\Field(description: 'Venue name', group: 'ExcludeFiltersTest')]
    #[GraphQL\Field(group: 'IncludeFiltersTest')]
    #[GraphQL\Field(group: 'CriteriaEvent')]
    #[GraphQL\Field(group: 'AttributeLimit')]

    #[ORM\Column(type: "string", nullable: true)]
    private string|null $venue = null;

    #[GraphQL\Field(description: 'City name')]
    #[GraphQL\Field(group: 'CriteriaEvent')]
    #[GraphQL\Field(group: 'IncludeFiltersTest', includeFilters: [
        Filters::EQ,
        Filters::NEQ,
    ])]
    #[GraphQL\Field(group: 'AttributeLimit')]

    #[ORM\Column(type: "string", nullable: true)]
    private string|null $city = null;

    #[GraphQL\Field(description: 'State name')]
    #[GraphQL\Field(group: 'CriteriaEvent')]
    #[GraphQL\Field(group: 'IncludeFiltersTest', excludeFilters: [
        Filters::EQ,
    ])]
    #[ORM\Column(type: "string", nullable: true)]
    private string|null $state = null;

    #[GraphQL\Field(description: 'Performance date')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[GraphQL\Field(group: 'ExtractionMap', alias: 'date')]

    #[ORM\Column(type: "datetime", nullable: false)]
    private DateTime $performanceDate;

    #[GraphQL\Field(description: 'Primary key')]
    #[GraphQL\Field(group: 'ExcludeFiltersTest')]
    #[GraphQL\Field(group: 'IncludeFiltersTest')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[GraphQL\Field(group: 'ExtractionMap', alias: 'key')]

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private int $id;

    /** @var Collection<id, Recording> */
    #[GraphQL\Association(description: 'Recordings by artist')]
    #[GraphQL\Association(group: 'IncludeFiltersTest', includeFilters: [Filters::CONTAINS])]

    #[ORM\OneToMany(targetEntity: \ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording::class,
            mappedBy: "performance")]
    private Collection $recordings;

    #[GraphQL\Association(description: 'Artist entity')]
    #[ORM\ManyToOne(targetEntity: \ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist::class,
            inversedBy: "performances")]

    #[ORM\JoinColumn(name: "artist_id", referencedColumnName: "id", nullable: false)]
    private Artist $artist;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->recordings = new ArrayCollection();
    }

    /**
     * Set venue.
     */
    public function setVenue(string|null $venue = null): Performance
    {
        $this->venue = $venue;

        return $this;
    }

    /**
     * Get venue.
     */
    public function getVenue(): string|null
    {
        return $this->venue;
    }

    /**
     * Set city.
     */
    public function setCity(string|null $city = null): Performance
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     */
    public function getCity(): string|null
    {
        return $this->city;
    }

    /**
     * Set state.
     */
    public function setState(string|null $state = null): Performance
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get state.
     */
    public function getState(): string|null
    {
        return $this->state;
    }

    /**
     * Set performanceDate.
     */
    public function setPerformanceDate(DateTime $performanceDate): Performance
    {
        $this->performanceDate = $performanceDate;

        return $this;
    }

    /**
     * Get performanceDate.
     */
    public function getPerformanceDate(): DateTime
    {
        return $this->performanceDate;
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Add recording.
     */
    public function addRecording(Recording $recording): Performance
    {
        $this->recordings[] = $recording;

        return $this;
    }

    /**
     * Remove recording.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRecording(Recording $recording): bool
    {
        return $this->recordings->removeElement($recording);
    }

    /**
     * Get recordings.
     *
     * @return Collection<id, Recording>
     */
    public function getRecordings(): Collection
    {
        return $this->recordings;
    }

    /**
     * Set artist.
     */
    public function setArtist(Artist $artist): Performance
    {
        $this->artist = $artist;

        return $this;
    }

    /**
     * Get artist.
     */
    public function getArtist(): Artist
    {
        return $this->artist;
    }
}
