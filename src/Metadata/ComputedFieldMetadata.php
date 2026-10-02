<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

/**
 * The metadata of a computed field exposed with #[ComputedField]
 */
final readonly class ComputedFieldMetadata
{
    public function __construct(
        public string $method,
        public string $type,
        public string $name,
        public string|null $description,
        public bool $list,
    ) {
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @throws MetadataException
     */
    public static function fromArray(array $array, string $context): self
    {
        $reader = new ArrayReader($array, $context);

        return new self(
            $reader->string('method'),
            $reader->string('type'),
            $reader->string('name'),
            $reader->nullableString('description'),
            $reader->bool('list'),
        );
    }

    /** @return array{method: string, type: string, name: string, description: string|null, list: bool} */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'list' => $this->list,
        ];
    }
}
