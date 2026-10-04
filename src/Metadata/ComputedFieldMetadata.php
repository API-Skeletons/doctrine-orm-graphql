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
    /**
     * @param array<string, ComputedFieldArgumentMetadata> $args           The arguments, by name: the method's
     *                                                                     parameters
     * @param string|null                                  $expression     The DQL expression of the value, by
     *                                                                     which the field is filtered and sorted
     * @param list<string>                                 $excludeFilters The filters excluded for the field
     */
    public function __construct(
        public string $method,
        public string $type,
        public string $name,
        public string|null $description,
        public bool $list,
        public array $args,
        public string|null $expression = null,
        public array $excludeFilters = [],
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
            $reader->has('expression') ? $reader->string('expression') : null,
            $reader->has('excludeFilters') ? $reader->stringList('excludeFilters') : [],
        );
    }

    /**
     * The expression and the excluded filters are exported only when the
     * field has them
     *
     * @return array{method: string, type: string, name: string, description: string|null, list: bool, args: array<string, array{type: string, nullable: bool, default?: int|float|string|bool}>, expression?: string, excludeFilters?: list<string>}
     */
    public function toArray(): array
    {
        $array = [
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

        if ($this->expression !== null) {
            $array['expression'] = $this->expression;
        }

        if ($this->excludeFilters !== []) {
            $array['excludeFilters'] = $this->excludeFilters;
        }

        return $array;
    }
}
