<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryAnimal;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityRepository;

use function strrchr;
use function substr;

/**
 * The repository of animals, and of a dog and a bird, which have none of
 * their own
 *
 * @extends EntityRepository<RepositoryAnimal>
 */
class RepositoryAnimalRepository extends EntityRepository
{
    /**
     * The name of the repository which gave the value, and the animal's
     *
     * @param Collection<int|string, RepositoryAnimal> $animals
     *
     * @return array<int|string, string>
     */
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryInheritance', batch: true)]
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryInheritanceMissing', batch: true)]
    public function getSound(Collection $animals): array
    {
        $repository = substr((string) strrchr(static::class, '\\'), 1);

        return $animals->map(
            static fn (RepositoryAnimal $animal): string => $repository . ': ' . $animal->getName(),
        )->toArray();
    }
}
