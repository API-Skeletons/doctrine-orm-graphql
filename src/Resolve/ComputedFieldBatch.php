<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use Closure;
use Doctrine\Common\Collections\Collection;
use Throwable;

/**
 * The entities waiting for one batched computed field with the same
 * arguments, and once loaded, the value of each of them
 *
 * @internal
 */
final class ComputedFieldBatch
{
    /** @var array<int|string, object> The entities, by the database value of their identifier */
    private array $entities = [];

    /** @var array<array-key, mixed> The values, by the database value of the entity's identifier */
    private array $values = [];

    private bool $loaded = false;

    /** Thrown while loading, and so for every entity */
    private Throwable|null $error = null;

    /**
     * @param class-string                                                            $entityClass
     * @param Closure(Collection<int|string, object>, array<array-key, mixed>): mixed $extractor
     * @param array<array-key, mixed>                                                 $args
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly string $fieldName,
        public readonly Closure $extractor,
        public readonly array $args,
    ) {
    }

    public function add(int|string $key, object $entity): void
    {
        $this->entities[$key] = $entity;
    }

    /** @return array<int|string, object> */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /** @param array<array-key, mixed> $values */
    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    public function setError(Throwable $error): void
    {
        $this->error = $error;
    }

    /**
     * The value of an entity, or null when the method gave none
     *
     * @throws Throwable The error thrown while loading the batch.
     */
    public function getValue(int|string $key): mixed
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->values[$key] ?? null;
    }

    public function markLoaded(): void
    {
        $this->loaded = true;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }
}
