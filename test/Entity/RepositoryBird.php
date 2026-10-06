<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

/**
 * A bird, without a repository of its own.  It is not exposed in the
 * RepositoryInheritance group, so is resolved as an animal.
 */
#[GraphQL\Entity(group: 'RepositoryInheritanceMissing')]
#[ORM\Entity]
class RepositoryBird extends RepositoryAnimal
{
}
