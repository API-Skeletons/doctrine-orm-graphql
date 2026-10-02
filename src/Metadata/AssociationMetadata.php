<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

/**
 * The metadata of an association exposed with #[Association]
 */
final readonly class AssociationMetadata
{
    /** @param list<string> $excludeFilters */
    public function __construct(
        public string $name,
        public string|null $alias,
        public int|null $limit,
        public string|null $description,
        public array $excludeFilters,
        public string|null $eventName,
        public string $hydratorStrategy,
    ) {
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @throws MetadataException
     */
    public static function fromArray(string $name, array $array, string $context): self
    {
        $reader = new ArrayReader($array, $context);

        return new self(
            $name,
            $reader->nullableString('alias'),
            $reader->nullableNonNegativeInt('limit'),
            $reader->nullableString('description'),
            $reader->stringList('excludeFilters'),
            $reader->nullableString('eventName'),
            $reader->string('hydratorStrategy'),
        );
    }

    /** @return array{alias: string|null, limit: int|null, description: string|null, excludeFilters: list<string>, eventName: string|null, hydratorStrategy: string} */
    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'limit' => $this->limit,
            'description' => $this->description,
            'excludeFilters' => $this->excludeFilters,
            'eventName' => $this->eventName,
            'hydratorStrategy' => $this->hydratorStrategy,
        ];
    }
}
