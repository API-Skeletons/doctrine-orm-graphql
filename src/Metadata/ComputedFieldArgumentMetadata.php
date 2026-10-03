<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

/**
 * The metadata of an argument of a computed field: a parameter of its method
 */
final readonly class ComputedFieldArgumentMetadata
{
    /**
     * @param string                     $type     The registered type of the argument
     * @param bool                       $nullable Whether the argument may be null
     * @param int|float|string|bool|null $default  The default value, or null for none.  A null
     *                                             default is none, as an absent argument is null.
     */
    public function __construct(
        public string $type,
        public bool $nullable,
        public int|float|string|bool|null $default,
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
            $reader->string('type'),
            $reader->bool('nullable'),
            $reader->has('default') ? $reader->scalar('default') : null,
        );
    }

    /**
     * The default is exported only when the argument has one
     *
     * @return array{type: string, nullable: bool, default?: int|float|string|bool}
     */
    public function toArray(): array
    {
        $array = [
            'type' => $this->type,
            'nullable' => $this->nullable,
        ];

        if ($this->default !== null) {
            $array['default'] = $this->default;
        }

        return $array;
    }
}
