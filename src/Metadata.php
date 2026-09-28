<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ArrayObject;

use function array_key_exists;
use function get_debug_type;
use function is_int;

/**
 * This exists to wrap the metadata information
 *
 * The metadata is an array keyed by entity class.  toArray() exports it with
 * a format version so it can be cached, and fromArray() reads that export
 * back, rejecting a cache written in another format.
 *
 * @extends ArrayObject<string, mixed>
 */
final class Metadata extends ArrayObject
{
    /**
     * The version of the exported array's shape.  It changes only when the
     * shape changes, not with every release.
     */
    public const int FORMAT_VERSION = 1;

    public const string VERSION_KEY = '__version';

    /**
     * Export the metadata, with its format version, for caching
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [self::VERSION_KEY => self::FORMAT_VERSION] + $this->getArrayCopy();
    }

    /**
     * Read metadata exported by toArray().  An empty array is no metadata, to
     * be built from the entities.
     *
     * @param array<string, mixed> $array
     *
     * @throws MetadataException
     */
    public static function fromArray(array $array): self
    {
        if ($array === []) {
            return new self();
        }

        if (! array_key_exists(self::VERSION_KEY, $array)) {
            throw new MetadataException(
                'Cached metadata has no format version.  It was written by an earlier version of this library or '
                . 'with getArrayCopy().  Regenerate it with $driver->get(Metadata::class)->toArray().',
            );
        }

        $version = $array[self::VERSION_KEY];

        if (! is_int($version) || $version !== self::FORMAT_VERSION) {
            throw new MetadataException(
                'Cached metadata has format version ' . (is_int($version) ? $version : get_debug_type($version))
                . ' but format version ' . self::FORMAT_VERSION . ' is required.  Regenerate it with '
                . '$driver->get(Metadata::class)->toArray().',
            );
        }

        unset($array[self::VERSION_KEY]);

        return new self($array);
    }
}
