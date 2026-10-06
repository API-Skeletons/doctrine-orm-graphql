<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\GraphQL as GraphQLException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\DatabaseValue;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use GraphQL\Deferred;
use Throwable;

use function array_chunk;
use function is_array;
use function is_int;
use function is_scalar;
use function serialize;

/**
 * Load the values of a batched computed field, a method of a repository
 * which is given a Collection of entities and returns their values
 *
 * Rather than call the method for each entity, the entity is recorded and a
 * Deferred is returned.  When the executor resolves the Deferred values, the
 * method is called once for every entity waiting for the field with the same
 * arguments.  The entities are keyed by the database value of their
 * identifier, which is what a scalar query, such as one selecting
 * IDENTITY() of an association, returns, and the values are keyed the same
 * way.
 */
final class ComputedFieldBatchLoader
{
    use DatabaseValue;

    /** @var array<string, ComputedFieldBatch> */
    private array $batches = [];

    /** @param positive-int $chunkSize The most entities given to one call of the method */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly int $chunkSize = 1000,
    ) {
    }

    /**
     * Return a Deferred of the value of a batched computed field for an entity
     *
     * @param class-string                                                            $entityClass The exposed class of the entity
     * @param Closure(Collection<int|string, object>, array<array-key, mixed>): mixed $extractor
     * @param array<array-key, mixed>                                                 $args        The field's arguments
     */
    public function defer(
        object $entity,
        string $entityClass,
        string $fieldName,
        Closure $extractor,
        array $args,
    ): Deferred {
        $key   = $entityClass . "\0" . $fieldName . "\0" . serialize($args);
        $batch = $this->batches[$key] ??= new ComputedFieldBatch($entityClass, $fieldName, $extractor, $args);

        $entityKey = $this->keyOf($entity, $entityClass);
        $batch->add($entityKey, $entity);

        return new Deferred(function () use ($key, $batch, $entityKey): mixed {
            if (! $batch->isLoaded()) {
                // Entities registered from now on start a new batch
                if (($this->batches[$key] ?? null) === $batch) {
                    unset($this->batches[$key]);
                }

                // An error loading the batch is the error of every entity's field
                try {
                    $batch->setValues($this->load($batch));
                } catch (Throwable $error) {
                    $batch->setError($error);
                }

                $batch->markLoaded();
            }

            return $batch->getValue($entityKey);
        });
    }

    /**
     * The value of a batched computed field for one entity, given alone to
     * the method
     *
     * @param class-string                                                            $entityClass The exposed class of the entity
     * @param Closure(Collection<int|string, object>, array<array-key, mixed>): mixed $extractor
     * @param array<array-key, mixed>                                                 $args        The field's arguments
     */
    public function loadOne(
        object $entity,
        string $entityClass,
        string $fieldName,
        Closure $extractor,
        array $args,
    ): mixed {
        $batch = new ComputedFieldBatch($entityClass, $fieldName, $extractor, $args);
        $key   = $this->keyOf($entity, $entityClass);
        $batch->add($key, $entity);
        $batch->setValues($this->load($batch));

        return $batch->getValue($key);
    }

    /**
     * Call the method for each chunk of the batch's entities
     *
     * @return array<array-key, mixed>
     *
     * @throws GraphQLException
     */
    private function load(ComputedFieldBatch $batch): array
    {
        $values = [];

        foreach (array_chunk($batch->getEntities(), $this->chunkSize, true) as $chunk) {
            /** @psalm-suppress MixedAssignment The method may return anything */
            $result = ($batch->extractor)(new ArrayCollection($chunk), $batch->args);

            if ($result instanceof Collection) {
                $result = $result->toArray();
            }

            if (! is_array($result)) {
                throw new GraphQLException(
                    'Computed field ' . $batch->fieldName . ' of entity ' . $batch->entityClass . ' is batched, '
                    . 'but its method did not return an array or a Collection of values keyed by identifier.',
                );
            }

            $values += $result;
        }

        return $values;
    }

    /**
     * The database value of an entity's identifier, which keys it.  An
     * identifier whose database value is not an int or a string is keyed by
     * its string or serialized form.
     *
     * @param class-string $entityClass
     */
    private function keyOf(object $entity, string $entityClass): int|string
    {
        $metadata   = $this->entityManager->getClassMetadata($entityClass);
        $idField    = $metadata->getSingleIdentifierFieldName();
        $identifier = $this->entityManager->getUnitOfWork()->getEntityIdentifier($entity);

        /** @psalm-suppress MixedAssignment An identifier may be of any type */
        $id = self::databaseValueOf($this->entityManager, $metadata, $idField, $identifier[$idField]);

        return is_int($id) ? $id : (is_scalar($id) ? (string) $id : serialize($id));
    }
}
