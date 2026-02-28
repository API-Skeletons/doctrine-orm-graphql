<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Override;

use function array_keys;

/**
 * Extends DoctrineObject hydrator to support computed fields
 *
 * Computed fields are extracted by calling entity methods and merging
 * the results with regular field extraction. This maintains a single
 * resolution path through the FieldResolver.
 */
final class DoctrineObjectWithComputed extends DoctrineObject
{
    /**
     * Map of computed field names to extraction callables
     *
     * @var array<string, callable>
     */
    private array $computedFields = [];

    /**
     * Register a computed field for extraction
     *
     * @param string   $fieldName The GraphQL field name
     * @param callable $extractor Callable that accepts the entity and returns the field value
     */
    public function addComputedField(string $fieldName, callable $extractor): void
    {
        $this->computedFields[$fieldName] = $extractor;
    }

    /**
     * Check if a computed field is registered
     */
    public function hasComputedField(string $fieldName): bool
    {
        return isset($this->computedFields[$fieldName]);
    }

    /**
     * Get all registered computed field names
     *
     * @return string[]
     */
    public function getComputedFieldNames(): array
    {
        return array_keys($this->computedFields);
    }

    /**
     * Extract values from object, including computed fields
     *
     * This method calls the parent extract() to get regular Doctrine fields,
     * then adds computed field values by calling registered extractors.
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function extract(object $object): array
    {
        // Extract regular Doctrine fields using parent logic
        $data = parent::extract($object);

        // Add computed field values
        foreach ($this->computedFields as $fieldName => $extractor) {
            $data[$fieldName] = $extractor($object);
        }

        return $data;
    }
}
