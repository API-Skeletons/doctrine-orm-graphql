<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToBoolean;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * User
 */
#[GraphQL\Entity(description: 'User', typeName: 'user')]
#[GraphQL\Entity(description: 'User', typeName: 'user', group: 'testNonDefaultGroup')]
#[GraphQL\Entity(description: 'User', typeName: 'user', group: 'testPasswordFilter')]
#[GraphQL\Entity(group: 'CustomFieldStrategyTest')]
#[GraphQL\Entity(group: 'InputFactoryTest')]
#[GraphQL\Entity(group: 'InputFactoryAliasTest')]
#[GraphQL\Entity(group: 'StaticMetadata')]
#[ORM\Entity]
class User
{
    #[GraphQL\Field(description: 'User name')]
    #[GraphQL\Field(description: 'User name', group: 'testNonDefaultGroup')]
    #[GraphQL\Field(description: 'User name', group: 'testPasswordFilter')]
    #[GraphQL\Field(group: 'CustomFieldStrategyTest', hydratorStrategy: ToBoolean::class)]
    #[GraphQL\Field(group: 'InputFactoryTest')]
    #[GraphQL\Field(group: 'InputFactoryAliasTest', alias: 'nameAlias')]
    #[GraphQL\Field(group: 'StaticMetadata')]

    #[ORM\Column(type: 'string', nullable: false)]
    private string $name;

    #[GraphQL\Field(description: 'User email')]
    #[GraphQL\Field(group: 'InputFactoryTest')]
    #[GraphQL\Field(group: 'InputFactoryAliasTest')]

    #[ORM\Column(type: 'string', nullable: false)]
    private string $email;

    #[GraphQL\Field(description: 'User password')]
    #[GraphQL\Field(description: 'User password', group: 'testPasswordFilter')]

    #[GraphQL\Field(group: 'InputFactoryTest')]
    #[GraphQL\Field(group: 'InputFactoryAliasTest')]
    #[ORM\Column(type: 'string', nullable: false)]
    private string $password;

    #[GraphQL\Field(description: 'Primary key')]
    #[GraphQL\Field(description: 'Primary key', group: 'testNonDefaultGroup')]
    #[GraphQL\Field(group: 'InputFactoryTest')]
    #[GraphQL\Field(group: 'InputFactoryAliasTest')]

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private int $id;

    /** @var Collection<id, Recording> */
    #[GraphQL\Association(description: 'Recordings')]
    #[GraphQL\Association(group: 'CustomFieldStrategyTest', hydratorStrategy: AssociationDefault::class)]
    #[GraphQL\Association(group: 'StaticMetadata', excludeFilters: [Filters::EQ])]
    #[ORM\ManyToMany(
        targetEntity: Recording::class,
        inversedBy: 'users',
    )]
    #[ORM\JoinTable(name: 'RecordingToUser')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\InverseJoinColumn(name: 'recording_id', referencedColumnName: 'id', nullable: false)]
    private Collection $recordings;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->recordings = new ArrayCollection();
    }

    /**
     * Set name.
     */
    public function setName(string $name): User
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
     * Set email.
     */
    public function setEmail(string $email): User
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set password.
     */
    public function setPassword(string $password): User
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password.
     */
    public function getPassword(): string
    {
        return $this->password;
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
    public function addRecording(Recording $recording): User
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
}
