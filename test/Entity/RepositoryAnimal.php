<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryAnimalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * The root of a single table inheritance hierarchy with a repository.  A cat's
 * repository has fields of its own, a dog's only its parent's, and a bird has
 * no repository.
 */
#[GraphQL\Entity(group: 'RepositoryInheritance')]
#[GraphQL\Entity(group: 'RepositoryInheritanceMissing')]
#[ORM\Entity(repositoryClass: RepositoryAnimalRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap([
    'animal' => RepositoryAnimal::class,
    'cat' => RepositoryCat::class,
    'dog' => RepositoryDog::class,
    'bird' => RepositoryBird::class,
])]
class RepositoryAnimal
{
    #[GraphQL\Field(group: 'RepositoryInheritance')]
    #[GraphQL\Field(group: 'RepositoryInheritanceMissing')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'RepositoryInheritance')]
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
