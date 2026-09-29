<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\EntityFormerByValue;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity using the former byValue argument of the Entity attribute.  It is
 * not in test/Entity, whose metadata every test builds.
 */
#[GraphQL\Entity(byValue: false)]
#[ORM\Entity]
class FormerByValue
{
    #[GraphQL\Field]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;
}
