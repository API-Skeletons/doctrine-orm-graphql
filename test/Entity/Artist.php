<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\GraphQL\Entity;

use ApiSkeletons\Doctrine\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\GraphQL\Criteria\Filters;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Artist
 */
#[GraphQL\Entity(typeName: 'artist', description: 'Artists')]
#[GraphQL\Entity(group: 'ExcludeCriteriaTest', excludeCriteria: ['neq'])]
#[GraphQL\Entity(group: 'TypeNameTest')]
#[GraphQL\Entity(group: 'DuplicateGroup')]
#[GraphQL\Entity(group: 'DuplicateGroup')]
#[GraphQL\Entity(group: 'DuplicateGroupField')]
#[GraphQL\Entity(group: 'DuplicateGroupAssociation')]
#[GraphQL\Entity(group: 'FilterCriteriaEvent')]
#[GraphQL\Entity(group: 'AttributeLimit')]
#[GraphQL\Entity(group: 'LimitTest', limit: 2)]
#[ORM\Entity]
class Artist
{
    #[GraphQL\Field(description: 'Artist name')]
    #[GraphQL\Field(group: 'ExcludeCriteriaTest', excludeCriteria: ['eq'])]
    #[GraphQL\Field(group: 'TypeNameTest')]
    #[GraphQL\Field(group: 'DuplicateGroup')]
    #[GraphQL\Field(group: 'DuplicateGroup')]
    #[GraphQL\Field(group: 'DuplicateGroupField')]
    #[GraphQL\Field(group: 'DuplicateGroupField')]
    #[GraphQL\Field(group: 'FilterCriteriaEvent')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[ORM\Column(type: 'string', nullable: false)]
    private string $name;

    #[GraphQL\Field(description: 'Primary key')]
    #[GraphQL\Field(group: 'ExcludeCriteriaTest')]
    #[GraphQL\Field(group: 'TypeNameTest')]
    #[GraphQL\Field(group: 'FilterCriteriaEvent')]
    #[GraphQL\Field(group: 'LimitTest')]
    #[GraphQL\Field(group: 'AttributeLimit')]
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /** @var Collection<id, Performance> */
    #[GraphQL\Association(description: 'Performances')]
    #[GraphQL\Association(group: 'ExcludeCriteriaTest', excludeCriteria: [Filters::NEQ])]
    #[GraphQL\Association(group: 'IncludeCriteriaTest', includeCriteria: [Filters::EQ])]
    #[GraphQL\Association(group: 'DuplicateGroup')]
    #[GraphQL\Association(group: 'DuplicateGroup')]
    #[GraphQL\Association(group: 'DuplicateGroupAssociation')]
    #[GraphQL\Association(group: 'DuplicateGroupAssociation')]
    #[GraphQL\Association(group: 'FilterCriteriaEvent', filterCriteriaEventName: self::class . '.performances.filterCriteria')]
    #[GraphQL\Association(group: 'LimitTest')]
    #[GraphQL\Association(group: 'AttributeLimit', limit: 3)]

    #[ORM\OneToMany(targetEntity: 'ApiSkeletonsTest\Doctrine\GraphQL\Entity\Performance', mappedBy: 'artist')]
    private Collection $performances;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->performances = new ArrayCollection();
    }

    /**
     * Set name.
     */
    public function setName(string $name): Artist
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Add performance.
     */
    public function addPerformance(Performance $performance): Artist
    {
        $this->performances[] = $performance;

        return $this;
    }

    /**
     * Remove performance.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePerformance(Performance $performance): bool
    {
        return $this->performances->removeElement($performance);
    }

    /**
     * Get performances.
     *
     * @return Collection<id, Performance>
     */
    public function getPerformances(): Collection
    {
        return $this->performances;
    }
}
