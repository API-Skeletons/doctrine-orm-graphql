<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity whose to-one associations limit their own filters
 */
#[GraphQL\Entity(group: 'ToOneFilters')]
#[ORM\Entity]
class TestToOneFilters
{
    #[GraphQL\Field(group: 'ToOneFilters')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Association(group: 'ToOneFilters', excludeFilters: [Filters::EQ, Filters::IN])]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestToOneFilters|null $excluded = null;

    #[GraphQL\Association(group: 'ToOneFilters', includeFilters: [Filters::ISNULL])]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestToOneFilters|null $included = null;

    /** Only a filter a to-one association does not have, so it has none */
    #[GraphQL\Association(group: 'ToOneFilters', includeFilters: [Filters::CONTAINS])]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestToOneFilters|null $none = null;

    #[GraphQL\Association(group: 'ToOneFilters')]
    #[ORM\ManyToOne(targetEntity: self::class)]
    private TestToOneFilters|null $every = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getExcluded(): TestToOneFilters|null
    {
        return $this->excluded;
    }

    public function setExcluded(TestToOneFilters|null $excluded): self
    {
        $this->excluded = $excluded;

        return $this;
    }

    public function getIncluded(): TestToOneFilters|null
    {
        return $this->included;
    }

    public function getNone(): TestToOneFilters|null
    {
        return $this->none;
    }

    public function getEvery(): TestToOneFilters|null
    {
        return $this->every;
    }
}
