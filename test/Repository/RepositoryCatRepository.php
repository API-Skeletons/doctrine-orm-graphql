<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryCat;

/**
 * The repository of cats, which has the fields of its parent's and its own
 */
class RepositoryCatRepository extends RepositoryAnimalRepository
{
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryInheritance')]
    public function getLives(RepositoryCat $cat): int
    {
        return 9;
    }
}
