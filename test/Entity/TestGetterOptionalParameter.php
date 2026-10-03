<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A field whose getter has only an optional parameter, which extracting by
 * value calls without it
 */
#[GraphQL\Entity(group: 'GetterOptional')]
#[ORM\Entity]
class TestGetterOptionalParameter
{
    #[GraphQL\Field(group: 'GetterOptional')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[GraphQL\Field(group: 'GetterOptional')]
    #[ORM\Column(type: 'string')]
    private string $label = 'Label';

    public function getId(): int
    {
        return $this->id;
    }

    public function getLabel(string $suffix = '!'): string
    {
        return $this->label . $suffix;
    }
}
