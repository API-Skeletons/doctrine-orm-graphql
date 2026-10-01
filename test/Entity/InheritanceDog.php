<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A subclass in a single table inheritance hierarchy, exposed in the
 * InheritanceSubclass group only
 */
#[GraphQL\Entity(group: 'InheritanceSubclass')]
#[ORM\Entity]
class InheritanceDog extends InheritanceAnimal
{
    public function __construct(
        string $name,
        #[GraphQL\Field(group: 'InheritanceSubclass')]
        #[ORM\Column(type: 'string')]
        private string $breed,
    ) {
        parent::__construct($name);
    }

    public function getBreed(): string
    {
        return $this->breed;
    }
}
