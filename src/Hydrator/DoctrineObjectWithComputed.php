<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\ORM\EntityManager;
use Laminas\Hydrator\Filter\FilterProviderInterface;
use Override;

use function array_key_exists;
use function array_keys;
use function get_class_methods;
use function in_array;
use function method_exists;

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

    public function __construct(EntityManager $objectManager, bool $byValue = true, private bool $magicCall = false)
    {
        parent::__construct($objectManager, $byValue);
    }

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
     * Extract values from an object using by-value logic, with optional __call fallback.
     *
     * When magicCall is enabled and the entity implements __call, getters that have no
     * explicit method are invoked through __call so magic accessor patterns are honoured
     * during extraction. magicCall must be explicitly enabled via the Entity attribute.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function extractByValue(object $object): array
    {
        $data = parent::extractByValue($object);

        // __call fallback is opt-in and only applies when the entity has __call
        if (! $this->magicCall || ! method_exists($object, '__call')) {
            return $data;
        }

        $methods = get_class_methods($object);
        $filter  = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        foreach ($this->getFieldNames() as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }

            $getter        = 'get' . $this->inflector->classify($fieldName);
            $isser         = 'is' . $this->inflector->classify($fieldName);
            $dataFieldName = $this->computeExtractFieldName($fieldName);

            // Skip fields already handled by the parent (explicit getter/isser found,
            // or value already present in the extracted data)
            if (
                array_key_exists($dataFieldName, $data)
                || in_array($getter, $methods)
                || in_array($isser, $methods)
            ) {
                continue;
            }

            // Invoke getter via __call
            /** @psalm-suppress MixedMethodCall, MixedAssignment */
            $data[$dataFieldName] = $this->extractValue($fieldName, $object->$getter(), $object);
        }

        return $data;
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
            /** @psalm-suppress MixedAssignment */
            $data[$fieldName] = $extractor($object);
        }

        return $data;
    }
}
