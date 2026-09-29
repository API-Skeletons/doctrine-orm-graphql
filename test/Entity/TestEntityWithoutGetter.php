<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a field without a getter, and fields with each other kind
 * of accessor the hydrator reads by value
 */
#[GraphQL\Entity(group: 'MissingGetter')]
#[GraphQL\Entity(group: 'MissingGetterByReference', extractByValue: false)]
#[GraphQL\Entity(group: 'AccessorForms')]
#[ORM\Entity]
class TestEntityWithoutGetter
{
    #[GraphQL\Field(group: 'MissingGetter')]
    #[GraphQL\Field(group: 'MissingGetterByReference')]
    #[GraphQL\Field(group: 'AccessorForms')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** Without a getter */
    #[GraphQL\Field(group: 'MissingGetter')]
    #[GraphQL\Field(group: 'MissingGetterByReference')]
    #[ORM\Column(type: 'string')]
    private string $name = 'no getter';

    /** Read by isEnabled() */
    #[GraphQL\Field(group: 'AccessorForms')]
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    /** Read by isActive(), a method of the field's own name */
    #[GraphQL\Field(group: 'AccessorForms')]
    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function getId(): int
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
