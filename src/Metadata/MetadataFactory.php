<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\Common\MetadataFactory as CommonMetadataFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use League\Event\EventDispatcher;
use Override;
use ReflectionClass;
use RuntimeException;

use function assert;
use function ctype_upper;
use function lcfirst;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Build metadata for entities
 */
final class MetadataFactory extends CommonMetadataFactory
{
    public function __construct(
        protected Metadata $metadata,
        protected readonly EntityManager $entityManager,
        protected readonly Config $config,
        protected readonly GlobalEnable $globalEnable,
        protected readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * Build metadata for all entities and return it
     */
    public function getMetadata(): Metadata
    {
        if ($this->metadata->count()) {
            return $this->metadata;
        }

        // Fetch all entity classes from the entity manager
        $entityClasses = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $entityClasses[] = $metadata->getName();
        }

        // If global enable is set, use the GlobalEnable class to build metadata
        if ($this->config->getGlobalEnable()) {
            $this->metadata = $this->globalEnable->getMetadata($entityClasses);

            return $this->metadata;
        }

        // Build metadata for each entity class
        foreach ($entityClasses as $entityClass) {
            $reflectionClass = new ReflectionClass($entityClass);

            $entityClassMetadata = $this->entityManager
                ->getMetadataFactory()
                ->getMetadataFor($reflectionClass->getName());

            // If an Entity attribute does not exist, skip this entity
            if (! $this->buildMetadataForEntity($reflectionClass)) {
                continue;
            }

            $this->buildMetadataForFields($entityClassMetadata, $reflectionClass);
            $this->buildMetadataForAssociations($reflectionClass);
            $this->buildMetadataForComputedFields($reflectionClass);
        }

        // Fire the metadata.build event
        $this->eventDispatcher->dispatch(
            new MetadataEvent($this->metadata, 'metadata.build'),
        );

        return $this->metadata;
    }

    /**
     * Using the entity class attributes, generate the metadata.
     * The buildmetadata* functions exist to simplify the buildMetadata
     * function.
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private function buildMetadataForEntity(ReflectionClass $reflectionClass): bool
    {
        $entityInstance       = null;
        $entityAttributeFound = false;

        // Fetch attributes for the entity class filterd by Attribute\Entity
        foreach ($reflectionClass->getAttributes(Attribute\Entity::class) as $attribute) {
            $instance = $attribute->newInstance();

            // Only process attributes for the Config group
            if ($instance->getGroup() !== $this->config->getGroup()) {
                continue;
            }

            $entityAttributeFound = true;

            // Only one matching instance per group is allowed
            assert(
                ! $entityInstance,
                'Duplicate attribute found for entity '
                . $reflectionClass->getName() . ', group ' . $instance->getGroup(),
            );
            $entityInstance = $instance;

            // Save entity-level metadata
            $this->metadata[$reflectionClass->getName()] = [
                'entityClass' => $reflectionClass->getName(),
                'byValue' => $this->config->getGlobalByValue() ?? $instance->getByValue(),
                'limit' => $instance->getLimit(),
                'fields' => [],
                'excludeFilters' => Filters::toStringArray($instance->getExcludeFilters()),
                'description' => $instance->getDescription(),
                'typeName' => $instance->getTypeName() !== null
                    ? $this->appendGroupSuffix($instance->getTypeName()) :
                    $this->getTypeName($reflectionClass->getName()),
            ];
        }

        return $entityAttributeFound;
    }

    /**
     * Build the metadata for each field in an entity based on the Attribute\Field
     *
     * @param ClassMetadata<object>   $entityClassMetadata
     * @param ReflectionClass<object> $reflectionClass
     */
    private function buildMetadataForFields(
        ClassMetadata $entityClassMetadata,
        ReflectionClass $reflectionClass,
    ): void {
        foreach ($entityClassMetadata->getFieldNames() as $fieldName) {
            $fieldInstance   = null;
            $reflectionField = $reflectionClass->getProperty($fieldName);

            foreach ($reflectionField->getAttributes(Attribute\Field::class) as $attribute) {
                $instance = $attribute->newInstance();

                // Only process attributes for the same group
                if ($instance->getGroup() !== $this->config->getGroup()) {
                    continue;
                }

                // Only one matching instance per group is allowed
                assert(
                    ! $fieldInstance,
                    'Duplicate attribute found for field '
                    . $fieldName . ', group ' . $instance->getGroup(),
                );
                $fieldInstance = $instance;

                $fieldMetadata = [
                    'alias' => $instance->getAlias(),
                    'description' => $instance->getDescription(),
                    'type' => $instance->getType() ?? $entityClassMetadata->getTypeOfField($fieldName),
                    'hydratorStrategy' => $instance->getHydratorStrategy() ??
                        $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName)),
                    'excludeFilters' => Filters::toStringArray($instance->getExcludeFilters()),
                    'extractorMethod' => $instance->getExtractorMethod(),
                ];

                /** @psalm-suppress MixedArrayAssignment */
                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName] = $fieldMetadata;
            }
        }
    }

    /**
     * Build the metadata for each field in an entity based on the Attribute\Association
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private function buildMetadataForAssociations(
        ReflectionClass $reflectionClass,
    ): void {
        // Fetch attributes for associations
        $associationNames = $this->entityManager->getMetadataFactory()
            ->getMetadataFor($reflectionClass->getName())
            ->getAssociationNames();

        foreach ($associationNames as $associationName) {
            $associationInstance   = null;
            $reflectionAssociation = $reflectionClass->getProperty($associationName);

            foreach ($reflectionAssociation->getAttributes(Attribute\Association::class) as $attribute) {
                $instance = $attribute->newInstance();

                // Only process attributes for the same group
                if ($instance->getGroup() !== $this->config->getGroup()) {
                    continue;
                }

                // Only one matching instance per group is allowed
                assert(
                    ! $associationInstance,
                    'Duplicate attribute found for association '
                    . $associationName . ', group ' . $instance->getGroup(),
                );

                $associationInstance = $instance;

                $associationMetadata = [
                    'alias' => $instance->getAlias(),
                    'limit' => $instance->getLimit(),
                    'description' => $instance->getDescription(),
                    'excludeFilters' => Filters::toStringArray($instance->getExcludeFilters()),
                    'eventName' => $instance->getEventName(),
                    'hydratorStrategy' => $instance->getHydratorStrategy() ??
                        Strategy\AssociationDefault::class,
                ];

                /** @psalm-suppress MixedArrayAssignment */
                $this->metadata[$reflectionClass->getName()]['fields'][$associationName] = $associationMetadata;
            }
        }
    }

    /**
     * Build the metadata for computed fields in an entity based on ComputedField attributes
     *
     * @param ReflectionClass<object> $reflectionClass
     */
    private function buildMetadataForComputedFields(ReflectionClass $reflectionClass): void
    {
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            // Skip non-public, static, or constructor methods
            if (! $reflectionMethod->isPublic() || $reflectionMethod->isStatic() || $reflectionMethod->isConstructor()) {
                continue;
            }

            $computedFieldInstance = null;

            foreach ($reflectionMethod->getAttributes(Attribute\ComputedField::class) as $attribute) {
                $instance = $attribute->newInstance();

                // Only process attributes for the same group
                if ($instance->getGroup() !== $this->config->getGroup()) {
                    continue;
                }

                // Only one matching instance per group is allowed
                assert(
                    ! $computedFieldInstance,
                    'Duplicate ComputedField attribute found for method '
                    . $reflectionMethod->getName() . ', group ' . $instance->getGroup(),
                );
                $computedFieldInstance = $instance;

                // Determine field name: use explicit name or derive from method name
                $fieldName = $instance->getName() ?? $this->deriveFieldNameFromMethod($reflectionMethod->getName());

                // Validate no collision with existing fields
                if (isset($this->metadata[$reflectionClass->getName()]['fields'][$fieldName])) {
                    throw new RuntimeException(
                        'Computed field "' . $fieldName . '" collides with existing field in entity '
                        . $reflectionClass->getName(),
                    );
                }

                // Initialize computedFields array if not exists
                if (! isset($this->metadata[$reflectionClass->getName()]['computedFields'])) {
                    /** @psalm-suppress MixedArrayAssignment */
                    $this->metadata[$reflectionClass->getName()]['computedFields'] = [];
                }

                $computedFieldMetadata = [
                    'method' => $reflectionMethod->getName(),
                    'type' => $instance->getType(),
                    'name' => $fieldName,
                    'description' => $instance->getDescription(),
                ];

                /** @psalm-suppress MixedArrayAssignment */
                $this->metadata[$reflectionClass->getName()]['computedFields'][$fieldName] = $computedFieldMetadata;
            }
        }
    }

    /**
     * Derive GraphQL field name from method name by removing get/is prefix
     */
    private function deriveFieldNameFromMethod(string $methodName): string
    {
        // Handle getXxx() -> xxx
        if (str_starts_with($methodName, 'get') && strlen($methodName) > 3) {
            return lcfirst(substr($methodName, 3));
        }

        // Handle isXxx() -> isXxx (keep as-is for boolean getters)
        if (str_starts_with($methodName, 'is') && strlen($methodName) > 2 && ctype_upper($methodName[2])) {
            return $methodName;
        }

        // Fallback: use method name as-is
        return $methodName;
    }

    #[Override]
    protected function getConfig(): Config
    {
        return $this->config;
    }
}
