<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * The entity whose identifier a TestDerivedMember's identity is derived from
 */
#[GraphQL\Entity(group: 'DerivedIdentity')]
#[ORM\Entity]
class TestDerivedAccount
{
    #[GraphQL\Field(group: 'DerivedIdentity')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'DerivedIdentity')]
        #[ORM\Column(type: 'string')]
        private string $name,
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
}
