<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryInvalidRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity whose repository's computed fields are checked when the metadata
 * is built, each in a group of its own
 */
#[GraphQL\Entity(group: 'RepositoryWrongEntity')]
#[GraphQL\Entity(group: 'RepositoryNoParameter')]
#[GraphQL\Entity(group: 'RepositoryBatchArrayCollection')]
#[GraphQL\Entity(group: 'RepositoryBatchArray')]
#[GraphQL\Entity(group: 'RepositoryIntersection')]
#[GraphQL\Entity(group: 'RepositoryBuiltin')]
#[GraphQL\Entity(group: 'RepositoryAccepted')]
#[GraphQL\Entity(group: 'RepositoryPrivate')]
#[GraphQL\Entity(group: 'RepositoryBatchOnEntity')]
#[GraphQL\Entity(group: 'RepositoryArgs')]
#[ORM\Entity(repositoryClass: RepositoryInvalidRepository::class)]
class RepositoryInvalid
{
    #[GraphQL\Field(group: 'RepositoryAccepted')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }

    /** Only a method of a repository is batched */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBatchOnEntity', batch: true)]
    public function getCount(): int
    {
        return 1;
    }
}
