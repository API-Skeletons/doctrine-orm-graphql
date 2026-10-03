<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\FindPropertyInHierarchy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use League\Event\EventDispatcher;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function array_keys;
use function assert;
use function ctype_upper;
use function in_array;
use function is_array;
use function is_scalar;
use function lcfirst;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Build metadata for entities
 */
final class MetadataFactory
{
    use FindPropertyInHierarchy;

    public function __construct(
        protected Metadata $metadata,
        protected readonly EntityManager $entityManager,
        protected readonly Config $config,
        protected readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * Build metadata for all entities and return it
     */
    public function getMetadata(): Metadata
    {
        if ($this->metadata->count()) {
            $this->metadata->assertBuiltWith($this->config);

            return $this->metadata;
        }

        $this->metadata->setBuiltWith($this->config);

        // Fetch all entity classes from the entity manager
        $entityClasses = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $entityClasses[] = $metadata->getName();
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

            $this->assertPropertyAttributesAreMapped($entityClassMetadata, $reflectionClass);
            $this->buildMetadataForFields($entityClassMetadata, $reflectionClass);
            $this->buildMetadataForAssociations($reflectionClass);
            $this->buildMetadataForComputedFields($reflectionClass);
        }

        // Fire the metadata.build event
        $this->eventDispatcher->dispatch(
            new MetadataEvent($this->metadata, 'metadata.build'),
        );

        // After the event, as a listener may add or remove entities
        $this->assertReferencedEntitiesAreExposed($entityClasses);

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

        // Fetch attributes for the entity class filtered by Attribute\Entity
        foreach ($reflectionClass->getAttributes(Attribute\Entity::class) as $attribute) {
            // PHP would report only an unknown named parameter
            if (array_key_exists('byValue', $attribute->getArguments())) {
                throw new MetadataException(
                    'The byValue argument of the Entity attribute of ' . $reflectionClass->getName()
                    . ' is renamed extractByValue.',
                );
            }

            $instance = $attribute->newInstance();

            // Only process attributes for the Config group
            if ($instance->getGroup() !== $this->config->getGroup()) {
                continue;
            }

            $entityAttributeFound = true;

            // Only one matching instance per group is allowed
            if ($entityInstance) {
                throw new MetadataException(
                    'Duplicate attribute found for entity '
                    . $reflectionClass->getName() . ', group ' . $instance->getGroup(),
                );
            }

            $entityInstance = $instance;

            // Save entity-level metadata
            $this->metadata[$reflectionClass->getName()] = [
                'entityClass' => $reflectionClass->getName(),
                'extractByValue' => $this->config->getExtractByValue() ?? $instance->getExtractByValue(),
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
            // A field of an embeddable is named <property>.<field>.  It is
            // not exposed, as it is not a property of the entity.
            if (str_contains($fieldName, '.')) {
                continue;
            }

            $fieldInstance   = null;
            $reflectionField = $this->getMappedProperty($reflectionClass, $fieldName);

            foreach ($reflectionField->getAttributes(Attribute\Field::class) as $attribute) {
                $instance = $attribute->newInstance();

                // Only process attributes for the same group
                if ($instance->getGroup() !== $this->config->getGroup()) {
                    continue;
                }

                // Only one matching instance per group is allowed
                if ($fieldInstance) {
                    throw new MetadataException(
                        'Duplicate attribute found for field '
                        . $fieldName . ', group ' . $instance->getGroup(),
                    );
                }

                $fieldInstance = $instance;

                $fieldMetadata = [
                    'alias' => $instance->getAlias(),
                    'description' => $instance->getDescription(),
                    'type' => $instance->getType() ?? $entityClassMetadata->getTypeOfField($fieldName),
                    'hydratorStrategy' => $instance->getHydratorStrategy() ??
                        $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName)),
                    'excludeFilters' => Filters::toStringArray($instance->getExcludeFilters()),
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
        $classMetadata = $this->entityManager->getMetadataFactory()
            ->getMetadataFor($reflectionClass->getName());

        foreach ($classMetadata->getAssociationNames() as $associationName) {
            $associationInstance   = null;
            $reflectionAssociation = $this->getMappedProperty($reflectionClass, $associationName);

            foreach ($reflectionAssociation->getAttributes(Attribute\Association::class) as $attribute) {
                $instance = $attribute->newInstance();

                // Only process attributes for the same group
                if ($instance->getGroup() !== $this->config->getGroup()) {
                    continue;
                }

                // Only one matching instance per group is allowed
                if ($associationInstance) {
                    throw new MetadataException(
                        'Duplicate attribute found for association '
                        . $associationName . ', group ' . $instance->getGroup(),
                    );
                }

                $associationInstance = $instance;

                // A to-one association is not a connection, so it has neither an
                // event nor a limit, which would otherwise be silently ignored
                if ($classMetadata->isSingleValuedAssociation($associationName)) {
                    foreach (['eventName' => $instance->getEventName(), 'limit' => $instance->getLimit()] as $parameter => $value) {
                        if ($value !== null) {
                            throw new MetadataException(
                                'Association ' . $associationName . ' of entity ' . $reflectionClass->getName()
                                . ' is a to-one association, which has no ' . $parameter . '.  The ' . $parameter
                                . ' of an association applies to a collection.',
                            );
                        }
                    }
                }

                $associationMetadata = [
                    'alias' => $instance->getAlias(),
                    'limit' => $instance->getLimit(),
                    'description' => $instance->getDescription(),
                    'excludeFilters' => Filters::toStringArray($instance->getExcludeFilters()),
                    'eventName' => $instance->getEventName(),
                    'hydratorStrategy' => $instance->getHydratorStrategy() ?? Strategy\AssociationDefault::class,
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
            // A computed field is computed by calling a public, non-static method
            if (! $reflectionMethod->isPublic() || $reflectionMethod->isStatic() || $reflectionMethod->isConstructor()) {
                foreach ($reflectionMethod->getAttributes(Attribute\ComputedField::class) as $attribute) {
                    if ($attribute->newInstance()->getGroup() === $this->config->getGroup()) {
                        throw new MetadataException(
                            'Method ' . $reflectionMethod->getName() . ' of entity ' . $reflectionClass->getName()
                            . ' has a ComputedField attribute but is not a public, non-static method.',
                        );
                    }
                }

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
                if ($computedFieldInstance) {
                    throw new MetadataException(
                        'Duplicate ComputedField attribute found for method '
                        . $reflectionMethod->getName() . ', group ' . $instance->getGroup(),
                    );
                }

                $computedFieldInstance = $instance;

                // Determine field name: use explicit name or derive from method name
                $fieldName = $instance->getName() ?? $this->deriveFieldNameFromMethod($reflectionMethod->getName());

                // Validate no collision with existing fields
                if (isset($this->metadata[$reflectionClass->getName()]['fields'][$fieldName])) {
                    throw new MetadataException(
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
                    'list' => $instance->getList(),
                    'args' => $this->buildComputedFieldArguments($reflectionMethod, $instance, $reflectionClass->getName()),
                ];

                /** @psalm-suppress MixedArrayAssignment */
                $this->metadata[$reflectionClass->getName()]['computedFields'][$fieldName] = $computedFieldMetadata;
            }
        }
    }

    /**
     * The arguments of a computed field, one for each parameter of its
     * method, in order.  An int, float, string or bool parameter is an Int,
     * Float, String or Boolean argument; any other is of the type the
     * attribute's args give it.  A parameter which does not allow null is a
     * non-null argument, and its default value is the argument's.
     *
     * @return array<string, array{type: string, nullable: bool, default?: int|float|string|bool}>
     *
     * @throws MetadataException
     */
    private function buildComputedFieldArguments(
        ReflectionMethod $method,
        Attribute\ComputedField $attribute,
        string $entityClass,
    ): array {
        $types   = $attribute->getArgs();
        $context = ' of computed field method ' . $method->getName() . ' of entity ' . $entityClass;

        $arguments = [];
        foreach ($method->getParameters() as $parameter) {
            $name   = $parameter->getName();
            $prefix = 'Parameter $' . $name . $context;

            if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                throw new MetadataException(
                    $prefix . ' is variadic or passed by reference, which an argument cannot be.',
                );
            }

            $type = $types[$name] ?? self::argumentType($parameter);
            if ($type === null) {
                throw new MetadataException(
                    $prefix . ' is not an int, float, string or bool.  Give its type in the args of the '
                    . 'ComputedField attribute.',
                );
            }

            $argument = ['type' => $type, 'nullable' => $parameter->allowsNull()];

            // A null default is none: an absent argument is null
            /** @psalm-suppress MixedAssignment A default value may be of any type */
            $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            if ($default !== null) {
                if (! is_scalar($default)) {
                    throw new MetadataException(
                        $prefix . ' has a default value which is not an int, float, string or bool, which an '
                        . 'argument cannot have.',
                    );
                }

                $argument['default'] = $default;
            }

            $arguments[$name] = $argument;
        }

        foreach (array_keys($types) as $name) {
            if (! isset($arguments[$name])) {
                throw new MetadataException(
                    'The args of the ComputedField attribute' . $context . ' give the type of ' . $name
                    . ', which is not a parameter of the method.',
                );
            }
        }

        return $arguments;
    }

    /**
     * The registered type of a parameter which is an int, float, string or
     * bool, nullable or not
     */
    private static function argumentType(ReflectionParameter $parameter): string|null
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        return match ($type->getName()) {
            'int' => 'int',
            'float' => 'float',
            'string' => 'string',
            'bool' => 'boolean',
            default => null,
        };
    }

    /**
     * The entities an exposed association refers to, and a computed field of
     * an entity type returns, must be exposed in the group, as their types are
     * the fields' types.  Otherwise every query of the entity's type would
     * fail, with an error naming neither the field nor its entity.
     *
     * @param list<string> $entityClasses Every entity class of the entity manager
     *
     * @throws MetadataException
     */
    private function assertReferencedEntitiesAreExposed(array $entityClasses): void
    {
        $suffix = ', an entity which is not exposed in group ' . $this->config->getGroup()
            . '.  Add an Entity attribute of the group to it.';

        /** @psalm-suppress MixedAssignment The metadata built above, as a listener may have changed it */
        foreach ($this->metadata as $entityClass => $entityMetadata) {
            if (! is_array($entityMetadata) || ! in_array($entityClass, $entityClasses, true)) {
                continue;
            }

            $classMetadata = $this->entityManager->getClassMetadata($entityClass);
            $fields        = $entityMetadata['fields'] ?? [];
            $computed      = $entityMetadata['computedFields'] ?? [];
            assert(is_array($fields) && is_array($computed));

            foreach (array_keys($fields) as $fieldName) {
                if (! $classMetadata->hasAssociation((string) $fieldName)) {
                    continue;
                }

                $target = $classMetadata->getAssociationTargetClass((string) $fieldName);
                if (isset($this->metadata[$target])) {
                    continue;
                }

                throw new MetadataException(
                    'Association ' . $fieldName . ' of entity ' . $entityClass . ' refers to ' . $target . $suffix,
                );
            }

            /** @psalm-suppress MixedAssignment The metadata built above */
            foreach ($computed as $fieldName => $computedField) {
                $type = is_array($computedField) ? $computedField['type'] ?? null : null;

                if (! in_array($type, $entityClasses, true) || isset($this->metadata[$type])) {
                    continue;
                }

                throw new MetadataException(
                    'Computed field ' . $fieldName . ' of entity ' . $entityClass . ' is of type ' . $type . $suffix,
                );
            }
        }
    }

    /**
     * A Field attribute of the group must be on a mapped field, and an
     * Association attribute on an association, of the entity or a parent
     * class.  Otherwise it would be silently ignored.
     *
     * @param ClassMetadata<object>   $entityClassMetadata
     * @param ReflectionClass<object> $reflectionClass
     *
     * @throws MetadataException
     */
    private function assertPropertyAttributesAreMapped(
        ClassMetadata $entityClassMetadata,
        ReflectionClass $reflectionClass,
    ): void {
        for ($class = $reflectionClass; $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                // Each property once, in the class which declares it
                if ($property->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }

                $name   = $property->getName();
                $prefix = 'Property ' . $name . ' of entity ' . $reflectionClass->getName();

                // Doctrine's hasField() is true for an embeddable too
                $isEmbedded    = isset($entityClassMetadata->embeddedClasses[$name]);
                $isField       = ! $isEmbedded && $entityClassMetadata->hasField($name);
                $isAssociation = $entityClassMetadata->hasAssociation($name);
                $embedded      = ' is an embeddable, whose fields are not exposed.  Expose an embedded value with a ComputedField.';

                if (! $isField && $this->hasAttributeOfGroup($property, Attribute\Field::class)) {
                    throw new MetadataException($prefix . match (true) {
                        $isEmbedded => $embedded,
                        $isAssociation => ' is an association.  Expose it with an Association attribute, not a Field attribute.',
                        default => ' has a Field attribute but is not a mapped field.  Expose a value which is not a column with a ComputedField.',
                    });
                }

                if (! $isAssociation && $this->hasAttributeOfGroup($property, Attribute\Association::class)) {
                    throw new MetadataException($prefix . match (true) {
                        $isEmbedded => $embedded,
                        $isField => ' is a field.  Expose it with a Field attribute, not an Association attribute.',
                        default => ' has an Association attribute but is not a mapped association.',
                    });
                }
            }
        }
    }

    /**
     * Whether a property has an attribute of a class for the configured group
     *
     * @param class-string<T> $attributeClass
     *
     * @template T of Attribute\Field|Attribute\Association
     */
    private function hasAttributeOfGroup(ReflectionProperty $property, string $attributeClass): bool
    {
        foreach ($property->getAttributes($attributeClass) as $attribute) {
            if ($attribute->newInstance()->getGroup() === $this->config->getGroup()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the property for a mapped field or association.  A mapped
     * superclass or a parent entity may declare it.
     *
     * @param ReflectionClass<object> $reflectionClass
     *
     * @throws MetadataException
     */
    private function getMappedProperty(ReflectionClass $reflectionClass, string $propertyName): ReflectionProperty
    {
        $property = $this->findPropertyInHierarchy($reflectionClass, $propertyName);

        if ($property !== null) {
            return $property;
        }

        // Doctrine fails to load metadata for a mapped property which is not declared
        // @codeCoverageIgnoreStart
        throw new MetadataException(
            'Property ' . $propertyName . ' is mapped for entity '
            . $reflectionClass->getName() . ' but is not declared on it or any parent class',
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Derive GraphQL field name from method name by removing get/is prefix
     */
    private function deriveFieldNameFromMethod(string $methodName): string
    {
        // Handle getXxx() -> xxx, but not a method such as getaway()
        if (str_starts_with($methodName, 'get') && strlen($methodName) > 3 && ctype_upper($methodName[3])) {
            return lcfirst(substr($methodName, 3));
        }

        // Handle isXxx() -> isXxx (keep as-is for boolean getters)
        if (str_starts_with($methodName, 'is') && strlen($methodName) > 2 && ctype_upper($methodName[2])) {
            return $methodName;
        }

        // Fallback: use method name as-is
        return $methodName;
    }

    private function getDefaultStrategy(string|null $fieldType): string
    {
        // Set default strategy based on field type
        if (in_array($fieldType, ['tinyint', 'smallint', 'integer', 'int'])) {
            return Strategy\ToInteger::class;
        }

        if ($fieldType === 'float') {
            return Strategy\ToFloat::class;
        }

        // A decimal is a String, which keeps its precision
        if ($fieldType === 'decimal') {
            return Strategy\ToString::class;
        }

        if ($fieldType === 'boolean') {
            return Strategy\ToBoolean::class;
        }

        return Strategy\FieldDefault::class;
    }

    /**
     * Compute the GraphQL type name
     *
     * @param class-string $entityClass
     */
    private function getTypeName(string $entityClass): string
    {
        return $this->appendGroupSuffix($this->stripEntityPrefix($entityClass));
    }

    /**
     * Strip the configured entityPrefix from the type name
     *
     * @param class-string $entityClass
     */
    private function stripEntityPrefix(string $entityClass): string
    {
        $entityClassWithPrefix = $entityClass;
        $entityPrefix          = $this->config->getEntityPrefix();

        if ($entityPrefix !== null && strpos($entityClass, $entityPrefix) === 0) {
            $entityClassWithPrefix = substr($entityClass, strlen($entityPrefix));
        }

        return str_replace('\\', '_', $entityClassWithPrefix);
    }

    /**
     * Append the configured groupSuffix to the type name
     */
    private function appendGroupSuffix(string $entityClass): string
    {
        $groupSuffix = $this->config->getGroupSuffix();
        if ($groupSuffix !== null) {
            if ($groupSuffix !== '') {
                $entityClass .= '_' . $groupSuffix;
            }
        } else {
            $entityClass .= '_' . $this->config->getGroup();
        }

        return $entityClass;
    }
}
