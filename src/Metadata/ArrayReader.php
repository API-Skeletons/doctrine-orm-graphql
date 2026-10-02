<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;

use function array_is_list;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Read typed values from a metadata array, reporting a missing key or a value
 * of the wrong type with the entity and key it belongs to
 *
 * @internal
 */
final class ArrayReader
{
    /** @param array<array-key, mixed> $array */
    public function __construct(
        private readonly array $array,
        private readonly string $context,
    ) {
    }

    /** @throws MetadataException */
    public function string(string $key): string
    {
        $value = $this->value($key);

        if (! is_string($value)) {
            throw $this->invalid($key, 'a string', $value);
        }

        return $value;
    }

    /** @throws MetadataException */
    public function nullableString(string $key): string|null
    {
        $value = $this->value($key);

        if ($value !== null && ! is_string($value)) {
            throw $this->invalid($key, 'a string or null', $value);
        }

        return $value;
    }

    /** @throws MetadataException */
    public function int(string $key): int
    {
        $value = $this->value($key);

        if (! is_int($value)) {
            throw $this->invalid($key, 'an int', $value);
        }

        return $value;
    }

    /** @throws MetadataException */
    public function nullableInt(string $key): int|null
    {
        $value = $this->value($key);

        if ($value !== null && ! is_int($value)) {
            throw $this->invalid($key, 'an int or null', $value);
        }

        return $value;
    }

    /**
     * An int of at least 0, such as a limit, of which 0 is the default
     *
     * @throws MetadataException
     */
    public function nonNegativeInt(string $key): int
    {
        $value = $this->int($key);

        if ($value < 0) {
            throw $this->negative($key, $value);
        }

        return $value;
    }

    /** @throws MetadataException */
    public function nullableNonNegativeInt(string $key): int|null
    {
        $value = $this->nullableInt($key);

        if ($value !== null && $value < 0) {
            throw $this->negative($key, $value);
        }

        return $value;
    }

    /** @throws MetadataException */
    public function bool(string $key): bool
    {
        $value = $this->value($key);

        if (! is_bool($value)) {
            throw $this->invalid($key, 'a bool', $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     *
     * @throws MetadataException
     */
    public function stringList(string $key): array
    {
        $value = $this->value($key);

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->invalid($key, 'a list of strings', $value);
        }

        $strings = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                throw $this->invalid($key, 'a list of strings', $value);
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws MetadataException
     */
    public function array(string $key): array
    {
        $value = $this->value($key);

        if (! is_array($value)) {
            throw $this->invalid($key, 'an array', $value);
        }

        return $value;
    }

    /**
     * Read an array whose values are arrays, keyed by name.  Each value is
     * reported with $elementContext followed by its name.
     *
     * @return array<string, array<array-key, mixed>>
     *
     * @throws MetadataException
     */
    public function arrayOfArrays(string $key, string $elementContext): array
    {
        $arrays = [];
        foreach ($this->array($key) as $name => $value) {
            $name = (string) $name;

            if (! is_array($value)) {
                throw new MetadataException(
                    'Metadata for ' . $elementContext . $name . ' must be an array, '
                    . get_debug_type($value) . ' given.',
                );
            }

            $arrays[$name] = $value;
        }

        return $arrays;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->array);
    }

    /** @throws MetadataException */
    private function value(string $key): mixed
    {
        if (! $this->has($key)) {
            throw new MetadataException('Metadata for ' . $this->context . ' is missing the key ' . $key . '.');
        }

        return $this->array[$key];
    }

    private function negative(string $key, int $value): MetadataException
    {
        return new MetadataException(
            'Metadata for ' . $this->context . ' key ' . $key . ' must be at least 0, ' . $value . ' given.',
        );
    }

    private function invalid(string $key, string $expected, mixed $value): MetadataException
    {
        return new MetadataException(
            'Metadata for ' . $this->context . ' key ' . $key . ' must be ' . $expected . ', '
            . get_debug_type($value) . ' given.',
        );
    }
}
