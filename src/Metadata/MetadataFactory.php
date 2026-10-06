<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\ComputedFieldExpression;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\FindPropertyInHierarchy;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\QueryException;
use League\Event\EventDispatcher;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function ctype_upper;
use function in_array;
use function is_a;
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

    /**
     * The DQL of the filters which DQL applies to some expressions only, by
     * the forms they use.  A comparison, and so a between, which is applied as
     * two, applies to every expression.
     */
    private const array EXPRESSION_FILTER_FORMS = [
        ' IN (:validateList)' => [Filters::IN, Filters::NOTIN],
        ' IS NULL' => [Filters::ISNULL],
        " LIKE :validateValue ESCAPE '!'" => [Filters::CONTAINS, Filters::STARTSWITH, Filters::ENDSWITH],
    ];

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
            $this->buildMetadataForComputedFields($reflectionClass, $reflectionClass->getName());
            $this->buildMetadataForRepositoryComputedFields($entityClassMetadata, $reflectionClass->getName());
        }

        // Fire the metadata.build event
        $this->eventDispatcher->dispatch(
            new MetadataEvent($this->metadata, 'metadata.build'),
        );

        // After the event, as a listener may add or remove entities
        $this->assertReferencedEntitiesAreExposed($entityClasses);
        $this->assertSubclassesHaveRepositoryFields($entityClasses);
        $this->assertComputedFieldExpressionsAreValid($entityClasses);

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
     * Build the metadata for computed fields in an entity based on ComputedField
     * attributes on the methods of a class: the entity, or its repository
     *
     * @param ReflectionClass<T>    $reflectionClass The entity, or its repository
     * @param class-string          $entityClass
     * @param ClassMetadata<object> $classMetadata   The entity's class metadata, for a repository
     *
     * @template T of object
     */
    private function buildMetadataForComputedFields(
        ReflectionClass $reflectionClass,
        string $entityClass,
        ClassMetadata|null $classMetadata = null,
    ): void {
        $owner = $classMetadata === null
            ? 'entity ' . $entityClass
            : 'repository ' . $reflectionClass->getName() . ' of entity ' . $entityClass;

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            // A computed field is computed by calling a public, non-static method
            if (! $reflectionMethod->isPublic() || $reflectionMethod->isStatic() || $reflectionMethod->isConstructor()) {
                foreach ($reflectionMethod->getAttributes(Attribute\ComputedField::class) as $attribute) {
                    if ($attribute->newInstance()->getGroup() === $this->config->getGroup()) {
                        throw new MetadataException(
                            'Method ' . $reflectionMethod->getName() . ' of ' . $owner
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
                if (isset($this->metadata[$entityClass]['fields'][$fieldName])) {
                    throw new MetadataException(
                        'Computed field "' . $fieldName . '" collides with existing field in entity '
                        . $entityClass,
                    );
                }

                // Nor with another computed field, of the entity or its repository
                if (isset($this->metadata[$entityClass]['computedFields'][$fieldName])) {
                    throw new MetadataException(
                        'Computed field "' . $fieldName . '" of method ' . $reflectionMethod->getName() . ' of '
                        . $owner . ' collides with another computed field of the same name.',
                    );
                }

                if ($classMetadata === null && $instance->getBatch()) {
                    throw new MetadataException(
                        'Computed field "' . $fieldName . '" of ' . $owner . ' is batched, but only a method of '
                        . 'a repository is given a Collection of entities.',
                    );
                }

                if ($classMetadata !== null) {
                    $this->assertRepositoryMethodIsValid(
                        $reflectionMethod,
                        $instance->getBatch(),
                        $classMetadata,
                        'Computed field "' . $fieldName . '" of ' . $owner,
                    );
                }

                // Initialize computedFields array if not exists
                if (! isset($this->metadata[$entityClass]['computedFields'])) {
                    /** @psalm-suppress MixedArrayAssignment */
                    $this->metadata[$entityClass]['computedFields'] = [];
                }

                $computedFieldMetadata = [
                    'method' => $reflectionMethod->getName(),
                    'type' => $instance->getType(),
                    'name' => $fieldName,
                    'description' => $instance->getDescription(),
                    'list' => $instance->getList(),
                    'args' => $this->buildComputedFieldArguments(
                        $reflectionMethod,
                        $instance,
                        $owner,
                        $classMetadata !== null,
                    ),
                ];

                // Left out when unset, so the metadata of a field without them is unchanged
                if ($classMetadata !== null) {
                    $computedFieldMetadata['repository'] = true;
                }

                if ($instance->getBatch()) {
                    $computedFieldMetadata['batch'] = true;
                }

                if ($instance->getExpression() !== null) {
                    $computedFieldMetadata['expression'] = $instance->getExpression();
                }

                $excludeFilters = Filters::toStringArray($instance->getExcludeFilters());
                if ($excludeFilters !== []) {
                    $computedFieldMetadata['excludeFilters'] = $excludeFilters;
                }

                /** @psalm-suppress MixedArrayAssignment */
                $this->metadata[$entityClass]['computedFields'][$fieldName] = $computedFieldMetadata;
            }
        }
    }

    /**
     * Build the metadata for computed fields on the methods of an entity's
     * repository.  An entity without a repository of its own class has none.
     *
     * @param ClassMetadata<object> $classMetadata
     * @param class-string          $entityClass
     */
    private function buildMetadataForRepositoryComputedFields(ClassMetadata $classMetadata, string $entityClass): void
    {
        $repositoryClass = $classMetadata->customRepositoryClassName;

        if ($repositoryClass === null) {
            return;
        }

        $this->buildMetadataForComputedFields(new ReflectionClass($repositoryClass), $entityClass, $classMetadata);
    }

    /**
     * A repository's method is given the entity first, or with batch, a
     * Collection of entities, keyed by the database value of their identifier.
     * A batched entity must have a single identifier which is not an
     * association, to key it by.
     *
     * @param ClassMetadata<object> $classMetadata
     *
     * @throws MetadataException
     */
    private function assertRepositoryMethodIsValid(
        ReflectionMethod $method,
        bool $batch,
        ClassMetadata $classMetadata,
        string $prefix,
    ): void {
        $parameter = $method->getParameters()[0] ?? null;
        $given     = $batch ? Collection::class : $classMetadata->getName();

        if ($parameter === null || ! self::parameterAccepts($parameter, $given)) {
            throw new MetadataException(
                $prefix . ' is of method ' . $method->getName() . ', whose first parameter must accept '
                . ($batch ? 'any ' . Collection::class . ' of entities' : 'the entity') . '.',
            );
        }

        if (! $batch) {
            return;
        }

        $identifiers = $classMetadata->getIdentifierFieldNames();

        if (count($identifiers) !== 1 || $classMetadata->hasAssociation($identifiers[0])) {
            throw new MetadataException(
                $prefix . ' is batched, but the entity\'s identifier is composite or an association, which cannot '
                . 'key its entities.',
            );
        }
    }

    /**
     * Whether a parameter accepts any value of a class or interface: it has
     * no type, or a type which the class is, or mixed or object, or for a
     * Collection, iterable.  Any type of a union may accept it.
     *
     * @param class-string $class
     */
    private static function parameterAccepts(ReflectionParameter $parameter, string $class): bool
    {
        $type = $parameter->getType();

        if ($type === null) {
            return true;
        }

        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        foreach ($types as $member) {
            if (! $member instanceof ReflectionNamedType) {
                continue;
            }

            $accepts = $member->isBuiltin()
                ? in_array($member->getName(), ['mixed', 'object', ...($class === Collection::class ? ['iterable'] : [])], true)
                : is_a($class, $member->getName(), true);

            if ($accepts) {
                return true;
            }
        }

        return false;
    }

    /**
     * The arguments of a computed field, one for each parameter of its
     * method, in order.  An int, float, string or bool parameter is an Int,
     * Float, String or Boolean argument; any other is of the type the
     * attribute's args give it.  A parameter which does not allow null is a
     * non-null argument, and its default value is the argument's.  The first
     * parameter of a repository's method is the entity, not an argument.
     *
     * @return array<string, array{type: string, nullable: bool, default?: int|float|string|bool}>
     *
     * @throws MetadataException
     */
    private function buildComputedFieldArguments(
        ReflectionMethod $method,
        Attribute\ComputedField $attribute,
        string $owner,
        bool $isRepository,
    ): array {
        $types   = $attribute->getArgs();
        $context = ' of computed field method ' . $method->getName() . ' of ' . $owner;

        $parameters = $method->getParameters();
        if ($isRepository) {
            array_shift($parameters);
        }

        $arguments = [];
        foreach ($parameters as $parameter) {
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
     * Doctrine gives an entity the repository of a parent entity only when
     * the parent is a mapped superclass, so a subclass reads only its own
     * repository's computed fields.  A row of an exposed subclass is resolved
     * by the subclass's fields, even where its parent's type is queried, so
     * a subclass without a computed field of its exposed parent's repository
     * would silently give null for it.
     *
     * @param list<string> $entityClasses Every entity class of the entity manager
     *
     * @throws MetadataException
     */
    private function assertSubclassesHaveRepositoryFields(array $entityClasses): void
    {
        /** @psalm-suppress MixedAssignment The metadata built above, as a listener may have changed it */
        foreach ($this->metadata as $entityClass => $entityMetadata) {
            if (! is_array($entityMetadata) || ! in_array($entityClass, $entityClasses, true)) {
                continue;
            }

            $computed = $entityMetadata['computedFields'] ?? [];
            assert(is_array($computed));

            foreach ($this->entityManager->getClassMetadata($entityClass)->parentClasses as $parent) {
                /** @psalm-suppress MixedAssignment The metadata built above */
                $parentMetadata = $this->metadata[$parent] ?? null;
                $parentComputed = is_array($parentMetadata) ? $parentMetadata['computedFields'] ?? [] : [];
                assert(is_array($parentComputed));

                /** @psalm-suppress MixedAssignment The metadata built above */
                foreach ($parentComputed as $fieldName => $computedField) {
                    if (! is_array($computedField) || ($computedField['repository'] ?? false) !== true || isset($computed[$fieldName])) {
                        continue;
                    }

                    throw new MetadataException(
                        'Entity ' . $entityClass . ' extends ' . $parent . ', whose repository gives computed field '
                        . $fieldName . ', but the repository of ' . $entityClass . ' does not.  Doctrine does not '
                        . 'give an entity its parent entity\'s repository: give ' . $entityClass . ' a repository '
                        . 'which extends its parent\'s.',
                    );
                }
            }
        }
    }

    /**
     * The expression of a computed field is checked when the metadata is
     * built, after the metadata.build event, so a bad one fails at startup
     * rather than when a client's query uses it.  The excluded filters of a
     * computed field apply only to one with an expression.
     *
     * DQL compares any expression, but takes a subquery, or an arithmetic
     * expression, for none of IN, IS NULL and LIKE.  The filters which use
     * them are excluded for an expression they cannot be applied to, so the
     * metadata, cached or not, has only the filters which apply.
     *
     * @param list<string> $entityClasses Every entity class of the entity manager
     *
     * @throws MetadataException
     */
    private function assertComputedFieldExpressionsAreValid(array $entityClasses): void
    {
        // The excluded filters of each field, by entity, set after the iteration
        $excludeFilters = [];

        /** @psalm-suppress MixedAssignment The metadata built above, as a listener may have changed it */
        foreach ($this->metadata as $entityClass => $entityMetadata) {
            if (! is_array($entityMetadata) || ! in_array($entityClass, $entityClasses, true)) {
                continue;
            }

            $computed = $entityMetadata['computedFields'] ?? [];
            assert(is_array($computed));

            /** @psalm-suppress MixedAssignment The metadata built above */
            foreach ($computed as $fieldName => $computedFieldArray) {
                if (
                    ! is_array($computedFieldArray)
                    || (! array_key_exists('expression', $computedFieldArray)
                        && ! array_key_exists('excludeFilters', $computedFieldArray))
                ) {
                    continue;
                }

                $computedField = ComputedFieldMetadata::fromArray(
                    $computedFieldArray,
                    'entity ' . $entityClass . ' computed field ' . $fieldName,
                );

                $unsupported = $this->assertComputedFieldExpressionIsValid(
                    $entityClass,
                    (string) $fieldName,
                    $computedField,
                    $entityClasses,
                );

                $excluded = array_values(array_unique([...$computedField->excludeFilters, ...$unsupported]));
                if ($excluded === $computedField->excludeFilters) {
                    continue;
                }

                $excludeFilters[$entityClass][$fieldName] = $excluded;
            }
        }

        foreach ($excludeFilters as $entityClass => $fields) {
            /** @psalm-suppress MixedAssignment The metadata built above */
            $entityMetadata = $this->metadata[$entityClass];
            assert(is_array($entityMetadata) && is_array($entityMetadata['computedFields']));

            foreach ($fields as $fieldName => $excluded) {
                assert(is_array($entityMetadata['computedFields'][$fieldName]));

                /** @psalm-suppress MixedArrayAssignment */
                $entityMetadata['computedFields'][$fieldName]['excludeFilters'] = $excluded;
            }

            $this->metadata[$entityClass] = $entityMetadata;
        }
    }

    /**
     * An expression is the value of a single scalar, uses only the field's
     * arguments, and is valid DQL.  It is parsed, without a database, in a
     * query which uses it twice, as a filter and as a sort, as a query may:
     * an alias written without a placeholder is then defined twice.  Returns
     * the filters DQL cannot apply to it.
     *
     * @param list<string> $entityClasses Every entity class of the entity manager
     *
     * @return list<string>
     *
     * @throws MetadataException
     */
    private function assertComputedFieldExpressionIsValid(
        string $entityClass,
        string $fieldName,
        ComputedFieldMetadata $computedField,
        array $entityClasses,
    ): array {
        $prefix     = 'Computed field ' . $fieldName . ' of entity ' . $entityClass;
        $expression = $computedField->expression;

        if ($expression === null) {
            throw new MetadataException(
                $prefix . ' has excluded filters but no expression.  A computed field is filtered only by its '
                . 'expression.',
            );
        }

        if ($computedField->list || in_array($computedField->type, $entityClasses, true)) {
            throw new MetadataException(
                $prefix . ' has an expression but is ' . ($computedField->list ? 'a list' : 'of an entity type')
                . ', which has no single value to filter or sort by.',
            );
        }

        foreach (ComputedFieldExpression::argumentNames($expression) as $name) {
            if (! isset($computedField->args[$name])) {
                throw new MetadataException(
                    $prefix . ' has an expression which uses {:' . $name . '}, but ' . $name . ' is not a parameter '
                    . 'of method ' . $computedField->method . '.',
                );
            }
        }

        $use = static fn (string $aliasPrefix): string => ComputedFieldExpression::substitute(
            $expression,
            'entity',
            $aliasPrefix,
            static fn (string $name): string => ':' . $aliasPrefix . $name,
        );

        $select = 'SELECT entity FROM ' . $entityClass . ' entity WHERE ';
        $dql    = 'SELECT entity, ' . $use('validate1_') . ' AS HIDDEN validateSort FROM ' . $entityClass . ' entity '
            . 'WHERE ' . $use('validate2_') . ' = :validateValue ORDER BY validateSort';

        try {
            $this->entityManager->createQuery($dql)->getAST();
        } catch (QueryException $exception) {
            throw new MetadataException(
                $prefix . ' has an expression which is not valid: ' . $exception->getMessage() . '  Every alias in '
                . 'an expression must be a placeholder, such as {entity} or {p}.',
                previous: $exception,
            );
        }

        $unsupported = [];
        foreach (self::EXPRESSION_FILTER_FORMS as $form => $filters) {
            try {
                $this->entityManager->createQuery($select . $use('validate3_') . $form)->getAST();
            } catch (QueryException) {
                $unsupported = [...$unsupported, ...Filters::toStringArray($filters)];
            }
        }

        return $unsupported;
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
