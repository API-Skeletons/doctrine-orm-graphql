<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

/**
 * The metadata of a field exposed with #[Field]
 */
final readonly class FieldMetadata
{
    /** @param list<string> $excludeFilters */
    public function __construct(
        public string $name,
        public string|null $alias,
        public string|null $description,
        public string $type,
        public string $hydratorStrategy,
        public array $excludeFilters,
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
            $reader->nullableString('description'),
            $reader->string('type'),
            $reader->string('hydratorStrategy'),
            $reader->stringList('excludeFilters'),
        );
    }

    /** @return array{alias: string|null, description: string|null, type: string, hydratorStrategy: string, excludeFilters: list<string>} */
    public function toArray(): array
    {
        return [
            'alias' => $this->alias,
            'description' => $this->description,
            'type' => $this->type,
            'hydratorStrategy' => $this->hydratorStrategy,
            'excludeFilters' => $this->excludeFilters,
        ];
    }
}
