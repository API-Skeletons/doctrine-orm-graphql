<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with an embeddable.  An embedded value is exposed by a computed
 * field.
 */
#[GraphQL\Entity(group: 'Embedded')]
#[ORM\Entity]
class TestEntityWithEmbedded
{
    #[GraphQL\Field(group: 'Embedded')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'Embedded')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Embedded(class: TestEmbeddableAddress::class)]
        private TestEmbeddableAddress $address,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): TestEmbeddableAddress
    {
        return $this->address;
    }

    #[GraphQL\ComputedField(type: 'string', group: 'Embedded')]
    public function getCity(): string
    {
        return $this->address->getCity();
    }
}
