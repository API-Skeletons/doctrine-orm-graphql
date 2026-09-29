<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Throwable;

use function spl_object_id;

/**
 * The sources waiting for one collection field with the same arguments, and
 * once loaded, the connection resolved for each of them
 *
 * The sources are held here, so their object ids cannot be reused while the
 * batch exists.
 *
 * @internal
 */
final class CollectionBatch
{
    /** @var array<int, array{object, int|string}> The source and its identifier, by object id */
    private array $sources = [];

    /** @var array<int, mixed[]> The resolved connection, by the source's object id */
    private array $results = [];

    private bool $loaded = false;

    /** Whether a source's page needs the number of its rows */
    private bool $needsCount = false;

    /** Thrown while loading, and so for every source */
    private Throwable|null $error = null;

    /**
     * @param class-string            $sourceClassName
     * @param class-string            $targetClassName
     * @param array<array-key, mixed> $args
     */
    public function __construct(
        public readonly Entity $targetEntity,
        public readonly string $sourceClassName,
        public readonly string $targetClassName,
        public readonly string $associationName,
        public readonly array $args,
    ) {
    }

    public function add(object $source, int|string $identifier, bool $needsCount): void
    {
        $this->sources[spl_object_id($source)] = [$source, $identifier];
        $this->needsCount                      = $this->needsCount || $needsCount;
    }

    /**
     * Whether the page of any source needs the number of its rows: for
     * totalCount, for a backward page, or for first: 0
     */
    public function needsCount(): bool
    {
        return $this->needsCount;
    }

    /** @return array<int, array{object, int|string}> */
    public function getSources(): array
    {
        return $this->sources;
    }

    /** @param mixed[] $result */
    public function setResult(object $source, array $result): void
    {
        $this->results[spl_object_id($source)] = $result;
    }

    public function setError(Throwable $error): void
    {
        $this->error = $error;
    }

    /**
     * @return mixed[]
     *
     * @throws Throwable The error thrown while loading the batch.
     */
    public function getResult(object $source): array
    {
        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->results[spl_object_id($source)];
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
