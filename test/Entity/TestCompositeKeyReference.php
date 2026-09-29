<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a to-one association to an entity with a composite identifier
 */
#[GraphQL\Entity(group: 'CompositeKeyTest')]
#[ORM\Entity]
class TestCompositeKeyReference
{
    #[GraphQL\Field(group: 'CompositeKeyTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Association(group: 'CompositeKeyTest')]
    #[ORM\ManyToOne(targetEntity: TestCompositeKeyEntity::class)]
    #[ORM\JoinColumn(name: 'first_id', referencedColumnName: 'firstId', nullable: true)]
    #[ORM\JoinColumn(name: 'second_id', referencedColumnName: 'secondId', nullable: true)]
    private TestCompositeKeyEntity|null $compositeKeyEntity = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompositeKeyEntity(): TestCompositeKeyEntity|null
    {
        return $this->compositeKeyEntity;
    }
}
