<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ArrayObject;

use function array_key_exists;
use function get_debug_type;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function var_export;

/**
 * This exists to wrap the metadata information
 *
 * The metadata is an array keyed by entity class.  toArray() exports it with
 * a format version and the config it was built with so it can be cached, and
 * fromArray() reads that export back, rejecting a cache written in another
 * format.  A driver rejects cached metadata built with another config.
 *
 * @extends ArrayObject<string, mixed>
 */
final class Metadata extends ArrayObject
{
    /**
     * The version of the exported array's shape.  It changes only when the
     * shape changes, not with every release.
     */
    public const int FORMAT_VERSION = 3;

    public const string VERSION_KEY = '__version';

    public const string CONFIG_KEY = '__config';

    /**
     * The config values the metadata was built with
     *
     * @var array{group: string, groupSuffix: string|null, entityPrefix: string|null, extractByValue: bool|null}|null
     */
    private array|null $builtWith = null;

    /**
     * The config values which the metadata depends on: the group selects the
     * attributes, the group suffix and entity prefix form the type names, and
     * the extractByValue option replaces the extractByValue of each entity
     *
     * @return array{group: string, groupSuffix: string|null, entityPrefix: string|null, extractByValue: bool|null}
     */
    public static function configOf(Config $config): array
    {
        return [
            'group' => $config->getGroup(),
            'groupSuffix' => $config->getGroupSuffix(),
            'entityPrefix' => $config->getEntityPrefix(),
            'extractByValue' => $config->getExtractByValue(),
        ];
    }

    /**
     * Record the config the metadata was built with
     */
    public function setBuiltWith(Config $config): void
    {
        $this->builtWith = self::configOf($config);
    }

    /**
     * Metadata read from a cache must have been built with the same config
     * values, or its type names, attributes and extractByValue do not match the
     * config
     *
     * @throws MetadataException
     */
    public function assertBuiltWith(Config $config): void
    {
        $differences = [];

        foreach (self::configOf($config) as $key => $value) {
            $builtWith = $this->builtWith[$key] ?? null;

            if ($builtWith === $value) {
                continue;
            }

            $differences[] = $key . ' ' . var_export($builtWith, true) . ' rather than ' . var_export($value, true);
        }

        if ($differences) {
            throw new MetadataException(
                'Cached metadata was built with ' . implode(', ', $differences) . '.  Regenerate it with '
                . '$driver->get(Metadata::class)->toArray() using this config.',
            );
        }
    }

    /**
     * Export the metadata, with its format version and the config it was
     * built with, for caching
     *
     * @return array<string, mixed>
     *
     * @throws MetadataException
     */
    public function toArray(): array
    {
        if ($this->builtWith === null) {
            throw new MetadataException('Metadata which was not built by a driver cannot be exported.');
        }

        return [self::VERSION_KEY => self::FORMAT_VERSION, self::CONFIG_KEY => $this->builtWith] + $this->getArrayCopy();
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

        $builtWith      = $array[self::CONFIG_KEY] ?? null;
        $group          = is_array($builtWith) ? $builtWith['group'] ?? null : null;
        $groupSuffix    = is_array($builtWith) ? $builtWith['groupSuffix'] ?? null : null;
        $entityPrefix   = is_array($builtWith) ? $builtWith['entityPrefix'] ?? null : null;
        $extractByValue = is_array($builtWith) ? $builtWith['extractByValue'] ?? null : null;

        if (
            ! is_array($builtWith)
            || ! array_key_exists('groupSuffix', $builtWith)
            || ! array_key_exists('entityPrefix', $builtWith)
            || ! array_key_exists('extractByValue', $builtWith)
            || ! is_string($group)
            || ($groupSuffix !== null && ! is_string($groupSuffix))
            || ($entityPrefix !== null && ! is_string($entityPrefix))
            || ($extractByValue !== null && ! is_bool($extractByValue))
        ) {
            throw new MetadataException(
                'Cached metadata has no valid ' . self::CONFIG_KEY . ' key of the config it was built with.  '
                . 'Regenerate it with $driver->get(Metadata::class)->toArray().',
            );
        }

        unset($array[self::VERSION_KEY], $array[self::CONFIG_KEY]);

        $metadata            = new self($array);
        $metadata->builtWith = [
            'group' => $group,
            'groupSuffix' => $groupSuffix,
            'entityPrefix' => $entityPrefix,
            'extractByValue' => $extractByValue,
        ];

        return $metadata;
    }
}
