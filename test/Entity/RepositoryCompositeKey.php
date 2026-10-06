<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a composite identifier, which cannot key a batch
 */
#[GraphQL\Entity(group: 'RepositoryBatchKey')]
#[ORM\Entity(repositoryClass: RepositoryKeyRepository::class)]
class RepositoryCompositeKey
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $firstId,
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $secondId,
    ) {
    }

    public function getFirstId(): int
    {
        return $this->firstId;
    }

    public function getSecondId(): int
    {
        return $this->secondId;
    }
}
