<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity whose identifier is an association, which cannot key a batch
 */
#[GraphQL\Entity(group: 'RepositoryBatchDerivedKey')]
#[ORM\Entity(repositoryClass: RepositoryKeyRepository::class)]
class RepositoryDerivedKey
{
    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: RepositoryInvalid::class)]
        private RepositoryInvalid $owner,
    ) {
    }

    public function getOwner(): RepositoryInvalid
    {
        return $this->owner;
    }
}
