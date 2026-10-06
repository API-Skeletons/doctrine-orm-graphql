<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\Strategy;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\FindPropertyInHierarchy;
use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Laminas\Hydrator\Filter\FilterProviderInterface;
use LogicException;
use Override;

use function array_key_exists;
use function array_keys;
use function get_class_methods;
use function in_array;
use function method_exists;

/**
 * Extends DoctrineObject hydrator to support computed fields
 *
 * Computed fields are extracted by calling entity methods.  extract()
 * merges them with the regular fields; the FieldResolver extracts the regular
 * fields and computes a computed field only when it is queried.
 */
final class DoctrineObjectWithComputed extends DoctrineObject
{
    use FindPropertyInHierarchy;

    /**
     * Map of computed field names to extraction callables
     *
     * @var array<string, callable(object, array<array-key, mixed>): mixed>
     */
    private array $computedFields = [];

    /**
     * The names of the computed fields which have arguments
     *
     * @var array<string, true>
     */
    private array $computedFieldsWithArguments = [];

    /**
     * The batch extractors of the batched computed fields, by name
     *
     * @var array<string, Closure(Collection<int|string, object>, array<array-key, mixed>): mixed>
     */
    private array $batchExtractors = [];

    /**
     * Register a computed field for extraction: the GraphQL field name, a
     * callable that accepts the entity and the field's arguments and returns
     * its value, and whether the field has arguments.  A batched field also
     * has a batch extractor, a Closure that accepts a Collection of entities
     * and the field's arguments and returns their values, keyed by identifier.
     *
     * @param callable(object, array<array-key, mixed>): mixed                               $extractor
     * @param (Closure(Collection<int|string, object>, array<array-key, mixed>): mixed)|null $batchExtractor
     */
    public function addComputedField(
        string $fieldName,
        callable $extractor,
        bool $hasArguments = false,
        Closure|null $batchExtractor = null,
    ): void {
        $this->computedFields[$fieldName] = $extractor;

        if ($batchExtractor !== null) {
            $this->batchExtractors[$fieldName] = $batchExtractor;
        }

        if (! $hasArguments) {
            return;
        }

        $this->computedFieldsWithArguments[$fieldName] = true;
    }

    /**
     * Check if a computed field is registered
     */
    public function hasComputedField(string $fieldName): bool
    {
        return isset($this->computedFields[$fieldName]);
    }

    /**
     * Whether a computed field has arguments, so its value depends on them
     */
    public function hasComputedFieldArguments(string $fieldName): bool
    {
        return isset($this->computedFieldsWithArguments[$fieldName]);
    }

    /**
     * The batch extractor of a batched computed field, which is given a
     * Collection of entities, or null for a field which is not batched
     *
     * @return (Closure(Collection<int|string, object>, array<array-key, mixed>): mixed)|null
     */
    public function getBatchExtractor(string $fieldName): Closure|null
    {
        return $this->batchExtractors[$fieldName] ?? null;
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
     * Extract values from an object using by-value logic, with __call fallback.
     *
     * When neither getField() nor isField() exists as an explicit method, but the
     * entity implements __call, the getter is invoked through __call so magic
     * accessor patterns are honoured during extraction.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function extractByValue(object $object): array
    {
        $data = parent::extractByValue($object);

        // Nothing extra to do if the entity doesn't use __call
        if (! method_exists($object, '__call')) {
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
     * Extract values from an object using by-reference logic.
     *
     * The parent reads each property through the entity's own reflection
     * class, which cannot see a private property declared on a mapped
     * superclass or parent entity.  Walk the class hierarchy instead.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function extractByReference(object $object): array
    {
        // Psalm does not understand the ReflectionClass<covariant T> return
        // type of Doctrine\Persistence\Mapping\ClassMetadata::getReflectionClass()
        $refl   = $this->getClassMetadata()->getReflectionClass();
        $filter = $object instanceof FilterProviderInterface
            ? $object->getFilter()
            : $this->filterComposite;

        // Data cannot be extracted from a readonly class, as the parent reports
        if ($refl->isReadOnly()) {
            throw new LogicException(
                'this class "' . $object::class . '" is readonly, data can\'t be extracted',
            );
        }

        $data = [];

        foreach ($this->getFieldNames() as $fieldName) {
            if ($filter && ! $filter->filter($fieldName)) {
                continue;
            }

            /** @psalm-suppress InvalidArgument ReflectionClass<covariant T>, as above */
            $reflProperty = $this->findPropertyInHierarchy($refl, $fieldName);

            // Doctrine fails to load metadata for a mapped property which is not declared
            // @codeCoverageIgnoreStart
            if ($reflProperty === null) {
                throw new HydratorException(
                    'Property ' . $fieldName . ' is not declared on ' . $refl->getName() . ' or any parent class',
                );
            }

            // @codeCoverageIgnoreEnd

            // Readonly and uninitialized properties are skipped, as the parent does
            if ($reflProperty->isReadOnly() || ! $reflProperty->isInitialized($object)) {
                continue;
            }

            $dataFieldName = $this->computeExtractFieldName($fieldName);
            /** @psalm-suppress MixedAssignment */
            $data[$dataFieldName] = $this->extractValue($fieldName, $reflProperty->getValue($object), $object);
        }

        return $data;
    }

    /**
     * Extract values from object, including computed fields
     *
     * This method extracts the regular Doctrine fields, then adds computed
     * field values by calling registered extractors.  A computed field with
     * arguments has a value for each set of them, not one value, so it is
     * left out.  A batched computed field would be a query for each entity
     * extracted, so it is left out too.
     *
     * @return array<array-key, mixed>
     */
    #[Override]
    public function extract(object $object): array
    {
        $data = $this->extractFields($object);

        foreach ($this->getComputedFieldNames() as $fieldName) {
            if ($this->hasComputedFieldArguments($fieldName) || isset($this->batchExtractors[$fieldName])) {
                continue;
            }

            /** @psalm-suppress MixedAssignment */
            $data[$fieldName] = $this->extractComputedField($object, $fieldName);
        }

        return $data;
    }

    /**
     * Extract the regular Doctrine fields, without computing any computed
     * field.  A computed field may be costly, so the FieldResolver computes
     * one only when it is queried.
     *
     * @return array<array-key, mixed>
     */
    public function extractFields(object $object): array
    {
        return parent::extract($object);
    }

    /**
     * Compute a registered computed field
     *
     * @param array<array-key, mixed> $args The field's arguments
     */
    public function extractComputedField(object $object, string $fieldName, array $args = []): mixed
    {
        return ($this->computedFields[$fieldName])($object, $args);
    }

    /**
     * Extract a value through its strategy, passing the field name to
     * strategies which implement this library's Strategy interface.
     *
     * The Laminas hydrator does not pass the field name to strategies.
     * Strategies are shared instances, so without the field name a strategy
     * cannot tell which field it is extracting.  $name is the Doctrine field
     * name, not a GraphQL alias.  Strategies which implement only the Laminas
     * StrategyInterface are called without it.
     */
    #[Override]
    public function extractValue(string $name, mixed $value, object|null $object = null): mixed
    {
        if (! $this->hasStrategy($name)) {
            return $value;
        }

        $strategy = $this->getStrategy($name);

        if ($strategy instanceof Strategy) {
            return $strategy->extract($value, $object, $name);
        }

        return $strategy->extract($value, $object);
    }
}
