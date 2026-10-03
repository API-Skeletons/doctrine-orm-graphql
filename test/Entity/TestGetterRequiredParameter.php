<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A field whose getter requires a parameter, which extracting by value
 * cannot call: exposed in the GetterRequired group and not in the
 * GetterRequiredUnexposed group.  __call does not change that the getter is
 * called.
 */
#[GraphQL\Entity(group: 'GetterRequired')]
#[GraphQL\Entity(group: 'GetterRequiredUnexposed')]
#[ORM\Entity]
class TestGetterRequiredParameter
{
    #[GraphQL\Field(group: 'GetterRequired')]
    #[GraphQL\Field(group: 'GetterRequiredUnexposed')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'GetterRequired')]
    #[ORM\Column(type: 'string')]
    private string $title = 'Title';

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(string $locale): string
    {
        return $this->title . ' (' . $locale . ')';
    }

    /** @param mixed[] $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }
}
