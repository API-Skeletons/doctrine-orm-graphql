<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Override;

/**
 * Take no action on an association.  This class exists to
 * differentiate associations inside generated config.
 *
 * The Doctrine hydrator requires a CollectionStrategyInterface for
 * collection-valued associations and sets the collection name, class
 * metadata and object on it.
 */
final class AssociationDefault implements
    CollectionStrategyInterface,
    Strategy
{
    private string|null $collectionName = null;

    /** @var ClassMetadata<object>|null */
    private ClassMetadata|null $metadata = null;

    private object|null $object = null;

    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
    {
        return $value;
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): mixed
    {
        return $value;
    }

    #[Override]
    public function setCollectionName(string $collectionName): void
    {
        $this->collectionName = $collectionName;
    }

    #[Override]
    public function getCollectionName(): string
    {
        if ($this->collectionName === null) {
            throw new HydratorException('Collection name has not been set.');
        }

        return $this->collectionName;
    }

    /** @param ClassMetadata<object> $classMetadata */
    #[Override]
    public function setClassMetadata(ClassMetadata $classMetadata): void
    {
        $this->metadata = $classMetadata;
    }

    /** @return ClassMetadata<object> */
    #[Override]
    public function getClassMetadata(): ClassMetadata
    {
        if ($this->metadata === null) {
            throw new HydratorException('Class metadata has not been set.');
        }

        return $this->metadata;
    }

    #[Override]
    public function setObject(object $object): void
    {
        $this->object = $object;
    }

    #[Override]
    public function getObject(): object
    {
        if ($this->object === null) {
            throw new HydratorException('Object has not been set.');
        }

        return $this->object;
    }
}
