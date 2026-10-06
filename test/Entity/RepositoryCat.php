<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryCatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A cat, whose repository extends its parent's
 */
#[GraphQL\Entity(group: 'RepositoryInheritance')]
#[ORM\Entity(repositoryClass: RepositoryCatRepository::class)]
class RepositoryCat extends RepositoryAnimal
{
}
