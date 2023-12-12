<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Recording
 */
#[GraphQL\Entity(typeName: 'recording', description: 'Performance recordings')]
#[GraphQL\Entity(typeName: 'entitytestrecording', description: 'Entity Test Recordings', group: 'entityTest')]
#[GraphQL\Entity(group: 'IncludeFiltersTest')]
#[GraphQL\Entity(group: 'CustomFieldStrategyTest')]
#[ORM\Entity]
class Recording
{
    #[GraphQL\Field(description: 'Source')]
    #[GraphQL\Field(description: 'Entity Test Source', group: 'entityTest')]
    #[GraphQL\Field(group: 'CustomFieldStrategyTest')]
    #[GraphQL\Field(group: 'IncludeFiltersTest')]
    #[ORM\Column(type: 'text', nullable: false)]
    private string $source;

    #[GraphQL\Field(description: 'Primary key')]
    #[GraphQL\Field(description: 'Entity Test ID', group: 'entityTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    #[GraphQL\Association(description: 'Performance entity')]
    #[GraphQL\Association(description: 'Entity Test Performance', group: 'entityTest')]
    #[ORM\ManyToOne(targetEntity: 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance', inversedBy: 'recordings')]
    #[ORM\JoinColumn(name: 'performance_id', referencedColumnName: 'id', nullable: false)]
    private Performance $performance;

    /** @var Collection<id, User> */
    #[GraphQL\Association(description: 'Users')]
    #[GraphQL\Association(description: 'Entity Test Users', group: 'entityTest')]
    #[ORM\ManyToMany(targetEntity: 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User', mappedBy: 'recordings')]
    private Collection $users;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    /**
     * Set source.
     */
    public function setSource(string $source): Recording
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Get source.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set performance.
     */
    public function setPerformance(Performance $performance): Recording
    {
        $this->performance = $performance;

        return $this;
    }

    /**
     * Get performance.
     */
    public function getPerformance(): Performance
    {
        return $this->performance;
    }

    /**
     * Add user.
     */
    public function addUser(User $user): Recording
    {
        $this->users[] = $user;

        return $this;
    }

    /**
     * Remove user.
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeUser(User $user): bool
    {
        return $this->users->removeElement($user);
    }

    /**
     * Get users.
     *
     * @return Collection<id, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
