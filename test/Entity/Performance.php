<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\GraphQL\Entity;

use ApiSkeletons\Doctrine\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\GraphQL\Criteria\Filters;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Performance
 */
#[GraphQL\Entity(typeName: 'performance', description: 'Performances')]
#[GraphQL\Entity(group: 'ExcludeCriteriaTest', excludeCriteria: ['contains'])]
#[GraphQL\Entity(group: 'IncludeCriteriaTest', includeCriteria: [
    Filters::EQ,
    Filters::NEQ,
    Filters::CONTAINS,
])]
#[GraphQL\Entity(
    group: 'IncludeExcludeCriteriaTest',
    excludeCriteria: [Filters::IN],
    includeCriteria: [
        Filters::EQ,
        Filters::NEQ,
        Filters::CONTAINS,
    ],
)]
#[GraphQL\Entity(group: 'FilterCriteriaEvent')]
#[GraphQL\Entity(group: 'LimitTest')]
#[GraphQL\Entity(group: 'AttributeLimit')]
#[ORM\Entity]
class Performance
{
    #[GraphQL\Field(description: 'Venue name')]
    #[GraphQL\Field(description: 'Venue name', group: 'ExcludeCriteriaTest')]
    #[GraphQL\Field(group: 'IncludeCriteriaTest')]
    #[GraphQL\Field(group: 'FilterCriteriaEvent')]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[ORM\Column(type: 'string', nullable: true)]
    private string|null $venue = null;

    #[GraphQL\Field(description: 'City name')]
    #[GraphQL\Field(group: 'FilterCriteriaEvent')]
    #[GraphQL\Field(group: 'IncludeCriteriaTest', includeCriteria: [
        Filters::EQ,
        Filters::NEQ,
    ])]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[ORM\Column(type: 'string', nullable: true)]
    private string|null $city = null;

    #[GraphQL\Field(description: 'State name')]
    #[GraphQL\Field(group: 'FilterCriteriaEvent')]
    #[GraphQL\Field(group: 'IncludeCriteriaTest', excludeCriteria: [
        Filters::EQ,
    ])]
    #[ORM\Column(type: 'string', nullable: true)]
    private string|null $state = null;

    #[GraphQL\Field(description: 'Performance date')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[ORM\Column(type: 'datetime', nullable: false)]
    private DateTime $performanceDate;

    #[GraphQL\Field(description: 'Primary key')]
    #[GraphQL\Field(group: 'ExcludeCriteriaTest')]
    #[GraphQL\Field(group: 'IncludeCriteriaTest')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /** @var Collection<id, Recording> */
    #[GraphQL\Association(description: 'Recordings by artist')]
    #[GraphQL\Association(group: 'IncludeCriteriaTest', includeCriteria: [Filters::CONTAINS])]
    #[ORM\OneToMany(targetEntity: 'ApiSkeletonsTest\Doctrine\GraphQL\Entity\Recording', mappedBy: 'performance')]
    private Collection $recordings;

    #[GraphQL\Association(description: 'Artist entity')]
    #[ORM\ManyToOne(targetEntity: 'ApiSkeletonsTest\Doctrine\GraphQL\Entity\Artist', inversedBy: 'performances')]
    #[ORM\JoinColumn(name: 'artist_id', referencedColumnName: 'id', nullable: false)]
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
