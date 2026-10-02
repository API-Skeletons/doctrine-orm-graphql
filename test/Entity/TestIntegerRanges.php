<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with fields of each integer type, signed and unsigned, and an
 * association to an integer identifier
 */
#[GraphQL\Entity(group: 'IntegerRanges')]
#[ORM\Entity]
class TestIntegerRanges
{
    #[GraphQL\Field(group: 'IntegerRanges')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'IntegerRanges')]
    #[ORM\Column(type: 'smallint')]
    private int $small = 1;

    #[GraphQL\Field(group: 'IntegerRanges')]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $unsignedInteger = 1;

    #[GraphQL\Field(group: 'IntegerRanges')]
    #[ORM\Column(type: 'bigint')]
    private int|string $big = 1;

    #[GraphQL\Field(group: 'IntegerRanges')]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int|string $unsignedBig = 1;

    #[GraphQL\Association(group: 'IntegerRanges')]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestIntegerRanges|null $parent = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getSmall(): int
    {
        return $this->small;
    }

    public function getUnsignedInteger(): int
    {
        return $this->unsignedInteger;
    }

    public function getBig(): int|string
    {
        return $this->big;
    }

    public function getUnsignedBig(): int|string
    {
        return $this->unsignedBig;
    }

    public function getParent(): TestIntegerRanges|null
    {
        return $this->parent;
    }
}
