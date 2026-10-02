<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A mapped superclass with a Field attribute on a property which is not
 * mapped
 */
#[ORM\MappedSuperclass]
abstract class TestMisplacedAttributesParent
{
    #[GraphQL\Field(group: 'FieldOnParent')]
    protected string $parentUnmapped = 'unmapped';

    public function getParentUnmapped(): string
    {
        return $this->parentUnmapped;
    }
}
