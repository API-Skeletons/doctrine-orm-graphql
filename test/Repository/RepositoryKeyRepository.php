<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityRepository;

/**
 * The repository of entities whose identifier cannot key a batch
 *
 * @extends EntityRepository<object>
 */
class RepositoryKeyRepository extends EntityRepository
{
    /**
     * @param Collection<int|string, object> $entities
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBatchKey', batch: true)]
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBatchDerivedKey', batch: true)]
    public function getCount(Collection $entities): array
    {
        return [];
    }
}
