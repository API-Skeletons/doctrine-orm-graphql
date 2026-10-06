<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryInvalid;
use ArrayAccess;
use Countable;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Traversable;

/**
 * Computed fields of RepositoryInvalid, each in a group of its own, whose
 * first parameter is checked
 *
 * @extends EntityRepository<RepositoryInvalid>
 */
class RepositoryInvalidRepository extends EntityRepository
{
    /** Of another entity */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryWrongEntity')]
    public function wrongEntity(RepositoryArtist $artist): int
    {
        return 1;
    }

    /** Given nothing */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryNoParameter')]
    public function noParameter(): int
    {
        return 1;
    }

    /**
     * Only a Collection is promised
     *
     * @param ArrayCollection<int|string, RepositoryInvalid> $entities
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBatchArrayCollection', batch: true)]
    public function batchArrayCollection(ArrayCollection $entities): array
    {
        return [];
    }

    /**
     * Not a Collection
     *
     * @param array<int|string, RepositoryInvalid> $entities
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBatchArray', batch: true)]
    public function batchArray(array $entities): array
    {
        return $entities;
    }

    /**
     * An intersection, and another entity
     *
     * @param (Countable&ArrayAccess<int, mixed>)|RepositoryArtist $entity
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryIntersection')]
    public function intersection((Countable&ArrayAccess)|RepositoryArtist $entity): int
    {
        return 1;
    }

    /** A scalar */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryBuiltin')]
    public function builtin(int $entity): int
    {
        return $entity;
    }

    /**
     * Accepted: no type
     *
     * @param RepositoryInvalid $entity
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryAccepted')]
    public function untyped($entity): int // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    {
        return 1;
    }

    /** Accepted: mixed */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryAccepted')]
    public function mixedEntity(mixed $entity): int
    {
        return 1;
    }

    /** Accepted: a union, one of whose types is the entity */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryAccepted')]
    public function union(RepositoryArtist|RepositoryInvalid $entity): int
    {
        return 1;
    }

    /**
     * Accepted: iterable, for a Collection
     *
     * @param iterable<int|string, RepositoryInvalid> $entities
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryAccepted', batch: true)]
    public function iterableBatch(iterable $entities): array
    {
        return [];
    }

    /**
     * Accepted: an interface which a Collection extends
     *
     * @param Traversable<int|string, RepositoryInvalid> $entities
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryAccepted', batch: true)]
    public function traversableBatch(Traversable $entities): array
    {
        return [];
    }

    /** Not public */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryPrivate')]
    protected function hidden(RepositoryInvalid $entity): int
    {
        return 1;
    }

    /** An argument whose type is not given */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryArgs')]
    public function since(RepositoryInvalid $entity, DateTimeImmutable $since): int
    {
        return 1;
    }
}
