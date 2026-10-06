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
     * @param bool                                         $repository     Whether the method is of the entity's
     *                                                                     repository, given the entity first
     * @param bool                                         $batch          Whether the repository's method is given
     *                                                                     a Collection of entities
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
        public bool $repository = false,
        public bool $batch = false,
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

        $repository = $reader->has('repository') && $reader->bool('repository');
        $batch      = $reader->has('batch') && $reader->bool('batch');

        if ($batch && ! $repository) {
            throw new MetadataException(
                'Metadata for ' . $context . ' is batched but not of a repository.  Only a method of a repository '
                . 'is batched.',
            );
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
            $repository,
            $batch,
        );
    }

    /**
     * Whether the field is of a repository and batched, the expression and
     * the excluded filters are exported only when the field has them
     *
     * @return array{method: string, type: string, name: string, description: string|null, list: bool, args: array<string, array{type: string, nullable: bool, default?: int|float|string|bool}>, repository?: true, batch?: true, expression?: string, excludeFilters?: list<string>}
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

        if ($this->repository) {
            $array['repository'] = true;
        }

        if ($this->batch) {
            $array['batch'] = true;
        }

        if ($this->expression !== null) {
            $array['expression'] = $this->expression;
        }

        if ($this->excludeFilters !== []) {
            $array['excludeFilters'] = $this->excludeFilters;
        }

        return $array;
    }
}
