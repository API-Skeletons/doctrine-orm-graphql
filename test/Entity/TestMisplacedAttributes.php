<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with an attribute in the wrong place in each group but Placed
 */
#[GraphQL\Entity(group: 'Placed')]
#[GraphQL\Entity(group: 'FieldOnAssociation')]
#[GraphQL\Entity(group: 'FieldOnUnmapped')]
#[GraphQL\Entity(group: 'FieldOnEmbedded')]
#[GraphQL\Entity(group: 'FieldOnParent')]
#[GraphQL\Entity(group: 'AssociationOnField')]
#[GraphQL\Entity(group: 'AssociationOnUnmapped')]
#[GraphQL\Entity(group: 'AssociationOnEmbedded')]
#[GraphQL\Entity(group: 'PrivateComputedField')]
#[GraphQL\Entity(group: 'StaticComputedField')]
#[ORM\Entity]
class TestMisplacedAttributes extends TestMisplacedAttributesParent
{
    #[GraphQL\Field(group: 'Placed')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'Placed')]
    #[GraphQL\Association(group: 'AssociationOnField')]
    #[ORM\Column(type: 'string')]
    private string $name = 'name';

    #[GraphQL\Association(group: 'Placed')]
    #[GraphQL\Field(group: 'FieldOnAssociation')]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestMisplacedAttributes|null $parent = null;

    #[GraphQL\Field(group: 'FieldOnUnmapped')]
    #[GraphQL\Association(group: 'AssociationOnUnmapped')]
    private string $unmapped = 'unmapped';

    #[GraphQL\Field(group: 'FieldOnEmbedded')]
    #[GraphQL\Association(group: 'AssociationOnEmbedded')]
    #[ORM\Embedded(class: TestEmbeddableAddress::class)]
    private TestEmbeddableAddress $address;

    public function __construct()
    {
        $this->address = new TestEmbeddableAddress('1 Main Street', 'Springfield');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): TestMisplacedAttributes|null
    {
        return $this->parent;
    }

    public function getUnmapped(): string
    {
        return $this->unmapped;
    }

    public function getAddress(): TestEmbeddableAddress
    {
        return $this->address;
    }

    #[GraphQL\ComputedField(type: 'string', group: 'Placed')]
    public function getLabel(): string
    {
        return $this->getSecret();
    }

    #[GraphQL\ComputedField(type: 'string', group: 'PrivateComputedField')]
    private function getSecret(): string
    {
        return $this->name;
    }

    #[GraphQL\ComputedField(type: 'string', group: 'StaticComputedField')]
    public static function getShared(): string
    {
        return 'shared';
    }
}
