<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use Override;
use ReflectionException;

use function assert;
use function is_array;
use function method_exists;
use function spl_object_hash;
use function sprintf;

/**
 * Copied from Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy
 *
 * @codeCoverageIgnore
 */
abstract class Collection implements CollectionStrategyInterface
{
    private string|null $collectionName = null;

    /** @var ClassMetadata<object>|null */
    private ClassMetadata|null $metadata = null;

    private object|null $object = null;

    private Inflector $inflector;

    public function __construct(Inflector|null $inflector = null)
    {
        $this->inflector = $inflector ?? InflectorFactory::create()->build();
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

    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param  mixed       $value  The original value.
     * @param  object|null $object (optional) The original object for context.
     *
     * @return mixed       Returns the value that should be extracted.
     */
    #[Override]
    public function extract(mixed $value, object|null $object = null): mixed
    {
        return $value;
    }

    protected function getInflector(): Inflector
    {
        return $this->inflector;
    }

    /**
     * Return the collection by value (using the public API)
     *
     * @return DoctrineCollection<array-key,object>
     *
     * @throws InvalidArgumentException
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    protected function getCollectionFromObjectByValue(): DoctrineCollection
    {
        $object = $this->getObject();
        $getter = 'get' . $this->getInflector()->classify($this->getCollectionName());

        if (! method_exists($object, $getter)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The getter %s to access collection %s in object %s does not exist',
                    $getter,
                    $this->getCollectionName(),
                    $object::class,
                ),
            );
        }

        /** @psalm-suppress MixedMethodCall, MixedAssignment */
        $collection = $object->$getter();

        if (is_array($collection)) {
            $collection = new ArrayCollection($collection);
        }

        assert($collection instanceof DoctrineCollection);

        return $collection;
    }

    /**
     * Return the collection by reference (not using the public API)
     *
     * @return DoctrineCollection<array-key,object>
     *
     * @throws InvalidArgumentException|ReflectionException
     */
    protected function getCollectionFromObjectByReference(): DoctrineCollection
    {
        $object = $this->getObject();
        /** @psalm-suppress UndefinedDocblockClass */
        $refl         = $this->getClassMetadata()->getReflectionClass();
        $reflProperty = $refl->getProperty($this->getCollectionName());

        $reflProperty->setAccessible(true);

        /** @psalm-suppress MixedReturnStatement */
        return $reflProperty->getValue($object);
    }

    /**
     * This method is used internally by array_udiff to check if two objects are equal, according to their
     * SPL hash. This is needed because the native array_diff only compare strings
     */
    protected function compareObjects(object $a, object $b): int
    {
        return spl_object_hash($a) <=> spl_object_hash($b);
    }
}
