<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A computed field named as a mapped property which is not exposed, which
 * has its own getter
 */
#[GraphQL\Entity(group: 'computedShadowTest')]
#[ORM\Entity]
class TestComputedShadowsProperty
{
    #[GraphQL\Field(group: 'computedShadowTest')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $test = 'property';

    #[ORM\Column(type: 'string')]
    private string $label = 'property';

    public function getId(): int
    {
        return $this->id;
    }

    public function getTest(): string
    {
        return $this->test;
    }

    #[GraphQL\ComputedField(type: 'string', name: 'test', group: 'computedShadowTest')]
    public function computeTest(): string
    {
        return 'computed';
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    #[GraphQL\ComputedField(type: 'string', name: 'label', group: 'computedShadowTest')]
    public function computeLabel(string $prefix): string
    {
        return $prefix . 'computed';
    }
}
