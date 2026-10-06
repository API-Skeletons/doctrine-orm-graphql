<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryDogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A dog, whose repository extends its parent's without fields of its own
 */
#[GraphQL\Entity(group: 'RepositoryInheritance')]
#[ORM\Entity(repositoryClass: RepositoryDogRepository::class)]
class RepositoryDog extends RepositoryAnimal
{
}
