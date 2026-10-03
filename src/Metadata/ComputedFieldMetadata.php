<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

use function array_map;

/**
 * The metadata of a computed field exposed with #[ComputedField]
 */
final readonly class ComputedFieldMetadata
{
    /** @param array<string, ComputedFieldArgumentMetadata> $args The arguments, by name: the method's parameters */
    public function __construct(
        public string $method,
        public string $type,
        public string $name,
        public string|null $description,
        public bool $list,
        public array $args,
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

        $args = [];
        foreach ($reader->arrayOfArrays('args', $context . ' argument ') as $name => $argArray) {
            $args[$name] = ComputedFieldArgumentMetadata::fromArray($argArray, $context . ' argument ' . $name);
        }

        return new self(
            $reader->string('method'),
            $reader->string('type'),
            $reader->string('name'),
            $reader->nullableString('description'),
            $reader->bool('list'),
            $args,
        );
    }

    /** @return array{method: string, type: string, name: string, description: string|null, list: bool, args: array<string, array{type: string, nullable: bool, default?: int|float|string|bool}>} */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'list' => $this->list,
            'args' => array_map(
                static fn (ComputedFieldArgumentMetadata $arg): array => $arg->toArray(),
                $this->args,
            ),
        ];
    }
}
