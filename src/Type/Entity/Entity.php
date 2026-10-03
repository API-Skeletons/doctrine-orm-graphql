<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\ComputedFieldMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\EntityMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\FieldResolver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Connection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ArrayAccess;
use Closure;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use GraphQL\Type\Definition\InputType;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Laminas\Hydrator\HydratorInterface;
use League\Event\EventDispatcher;
use ReflectionClass;

use function array_keys;
use function array_merge;
use function assert;
use function ctype_upper;
use function get_class_methods;
use function in_array;
use function is_iterable;
use function is_string;
use function ksort;
use function preg_replace;
use function str_starts_with;
use function substr;
use function ucwords;

/**
 * This class is used to build an ObjectType for an entity
 */
final class Entity
{
    /**
     * The extraction map, built once.  Null until it is built, so an empty map
     * for an entity without aliases is cached as well.
     *
     * @var array<string, string>|null
     */
    protected array|null $extractionMap   = null;
    protected ObjectType|null $objectType = null;
    private readonly EntityMetadata $entityMetadata;

    /**
     * @param array<array-key, mixed> $metadata The entity's metadata array
     *
     * @throws MetadataException
     */
    public function __construct(
        private readonly string|null $eventName,
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly EntityTypeContainer $entityTypeContainer,
        protected readonly EventDispatcher $eventDispatcher,
        protected readonly FieldResolver $fieldResolver,
        protected readonly FilterFactory $filterFactory,
        protected readonly HydratorContainer $hydratorContainer,
        protected readonly PaginationService $paginationService,
        protected readonly ResolveCollectionFactory $resolveCollectionFactory,
        protected readonly TypeContainer $typeContainer,
        protected readonly array $metadata,
    ) {
        $this->entityMetadata = EntityMetadata::fromArray($metadata);
    }

    /** @psalm-suppress MixedReturnStatement */
    public function getHydrator(): HydratorInterface
    {
        return $this->hydratorContainer->get($this->getEntityClass());
    }

    public function getTypeName(): string
    {
        return $this->entityMetadata->typeName;
    }

    public function getDescription(): string|null
    {
        return $this->entityMetadata->description;
    }

    /**
     * The entity's metadata array, including any keys a metadata.build
     * listener added
     *
     * @return mixed[]
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getEntityMetadata(): EntityMetadata
    {
        return $this->entityMetadata;
    }

    /** @return class-string */
    public function getEntityClass(): string
    {
        return $this->entityMetadata->entityClass;
    }

    /**
     * An extraction map is used to alias fields and associations using a
     * naming strategy in the hydrator
     *
     * Every field of the type, whether a field, an association or a computed
     * field, named by its alias if it has one, must have a unique name.
     * Otherwise one would silently replace another in the type and in the
     * hydrator's extraction.
     *
     * @return array<string, string>
     *
     * @throws MetadataException
     */
    public function getExtractionMap(): array
    {
        if ($this->extractionMap !== null) {
            return $this->extractionMap;
        }

        // Build into a local so a duplicate name does not leave a partial map
        $extractionMap = [];

        // The field, association or computed field named by each name
        $names = [];

        $fields = [
            'field' => $this->entityMetadata->fields,
            'association' => $this->entityMetadata->associations,
            'computed field' => $this->entityMetadata->computedFields,
        ];

        foreach ($fields as $kind => $kindFields) {
            foreach ($kindFields as $fieldName => $fieldMetadata) {
                $alias = $fieldMetadata instanceof ComputedFieldMetadata ? null : $fieldMetadata->alias;
                $name  = $alias ?? $fieldName;

                if (isset($names[$name])) {
                    throw new MetadataException(
                        'Duplicate field name "' . $name . '" in entity ' . $this->getEntityClass() . ': '
                        . $names[$name] . ' and ' . $kind . ' ' . $fieldName
                        . ($alias !== null ? ' aliased as "' . $alias . '"' : '')
                        . '.  Each field of a type must have a unique name.',
                    );
                }

                $names[$name] = $kind . ' ' . $fieldName . ($alias !== null ? ' aliased as "' . $alias . '"' : '');

                if ($alias === null) {
                    continue;
                }

                $extractionMap[$fieldName] = $alias;
            }
        }

        $this->extractionMap = $extractionMap;

        return $this->extractionMap;
    }

    /**
     * Build the type for the current entity
     *
     * @throws MappingException
     */
    public function getObjectType(): ObjectType
    {
        // The result of this function is cached in the objectType property.
        // Entity object types are not stored in the TypeContainer
        if ($this->objectType) {
            return $this->objectType;
        }

        $this->assertExtractable();

        $fields = $this->addFields();
        $fields = array_merge($fields, $this->addAssociations());
        $fields = array_merge($fields, $this->addComputedFields());

        // A custom event name gives a distinct type.  It is appended to the type
        // name with any character which is not valid in a GraphQL name replaced
        $typeName = $this->getTypeName();
        if ($this->eventName !== null) {
            $eventTypeName = preg_replace('/[^_a-zA-Z0-9]/', '_', $this->eventName);
            assert(is_string($eventTypeName));

            $typeName .= '_' . $eventTypeName;
        }

        $definition = new Definition([
            'name' => $typeName,
            'description' => $this->getDescription(),
            'fields' => static fn () => $fields,
            'resolveField' => $this->fieldResolver,
        ]);

        /**
         * Dispatch event to allow modifications to the ObjectType definition
         */
        $this->eventDispatcher->dispatch(
            new EntityDefinition($definition, $this->eventName ?? $this->getEntityClass() . '.definition'),
        );

        /**
         * If sortFields then resolve the fields and sort them
         */
        if ($this->config->getSortFields()) {
            if ($definition['fields'] instanceof Closure) {
                /** @psalm-suppress MixedAssignment */
                $definition['fields'] = $definition['fields']();
            }

            /** @psalm-suppress MixedArgument */
            ksort($definition['fields']);
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        $this->objectType = (new ReflectionClass(ObjectType::class))
            ->newLazyGhost(static function (ObjectType $object) use ($definition): void {
                /** @psalm-suppress DirectConstructorCall */
                $object->__construct($definition->getArrayCopy()); // @phpstan-ignore argument.type
            });

        return $this->objectType;
    }

    /**
     * Extracting by value, the hydrator reads a field with its getter, and
     * silently leaves out a field without one, which would always be null.
     * The getter is found as the hydrator finds it: getField(), isField(), a
     * field named isField() itself, or __call.
     *
     * @throws HydratorException
     */
    private function assertExtractable(): void
    {
        if (! $this->entityMetadata->extractByValue) {
            return;
        }

        $methods = get_class_methods($this->getEntityClass());
        if (in_array('__call', $methods, true)) {
            return;
        }

        $inflector = InflectorFactory::create()->build();

        foreach (array_keys([...$this->entityMetadata->fields, ...$this->entityMetadata->associations]) as $fieldName) {
            $getter = 'get' . $inflector->classify($fieldName);
            $isser  = 'is' . $inflector->classify($fieldName);

            if (
                in_array($getter, $methods, true)
                || in_array($isser, $methods, true)
                || (str_starts_with($fieldName, 'is') && ctype_upper(substr($fieldName, 2, 1)) && in_array($fieldName, $methods, true))
            ) {
                continue;
            }

            throw new HydratorException(
                'Field ' . $fieldName . ' of entity ' . $this->getEntityClass() . ' has no ' . $getter . '() or '
                . $isser . '() method, which extracting by value reads it with.  Add one, or extract the entity '
                . 'by reference with extractByValue: false.',
            );
        }
    }

    /** @return array<string, mixed> */
    protected function addFields(): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            $fieldMetadata = $this->entityMetadata->fields[$fieldName] ?? null;
            if ($fieldMetadata === null) {
                continue;
            }

            $type = $this->typeContainer->get($fieldMetadata->type);

            // An identifier or a column which is not nullable always has a value
            if ($this->config->getUseNonNullTypes() && ! $classMetadata->isNullable($fieldName)) {
                $type = self::nonNull($type);
            }

            $fields[$this->getExtractionMap()[$fieldName] ?? $fieldName] = [
                'type' => $type,
                'description' => $fieldMetadata->description,
            ];
        }

        return $fields;
    }

    /**
     * The non-null type of a type, which a custom type may already be
     */
    private static function nonNull(Type $type): Type
    {
        return $type instanceof NullableType ? Type::nonNull($type) : $type;
    }

    /**
     * Whether a to-one association always has a value: it is the owning side
     * and none of its join columns is nullable.  A join column is nullable
     * unless it says otherwise.
     *
     * @param array<string, mixed>|ArrayAccess<string, mixed> $associationMapping
     *
     * @psalm-suppress MixedAssignment, MixedArrayAccess
     */
    private static function isRequired(array|ArrayAccess $associationMapping): bool
    {
        $joinColumns = $associationMapping['joinColumns'] ?? [];

        if (! is_iterable($joinColumns) || $joinColumns === []) {
            return false;
        }

        foreach ($joinColumns as $joinColumn) {
            if (($joinColumn['nullable'] ?? true) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedArgument, MixedAssignment
     */
    protected function addAssociations(): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getAssociationNames() as $associationName) {
            $graphqlAssociation = $this->entityMetadata->associations[$associationName] ?? null;
            if ($graphqlAssociation === null) {
                continue;
            }

            $associationMetadata = $classMetadata->getAssociationMapping($associationName);
            if (
                in_array($associationMetadata['type'], [
                    ClassMetadata::ONE_TO_ONE,
                    ClassMetadata::MANY_TO_ONE,
                    ClassMetadata::TO_ONE,
                ])
            ) {
                $targetEntity = $associationMetadata['targetEntity'];
                $required     = $this->config->getUseNonNullTypes() && self::isRequired($associationMetadata);

                // The hydrator extracts an aliased association under its alias
                $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $graphqlAssociation, $required): array {
                    $entity = $this->entityTypeContainer->get($targetEntity);

                    // The association's description, else the target entity's
                    return [
                        'type' => $required ? Type::nonNull($entity->getObjectType()) : $entity->getObjectType(),
                        'description' => $graphqlAssociation->description ?? $entity->getDescription(),
                    ];
                };

                continue;
            }

            // Collections
            $targetEntity = $associationMetadata['targetEntity'];

            $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $associationName, $graphqlAssociation): array {
                $entity    = $this->entityTypeContainer->get($targetEntity);
                $shortName = $this->getTypeName() . '_' . ucwords($associationName);

                return [
                    'type' => $this->typeContainer->build(
                        Connection::class,
                        Connection::nameFor($shortName),
                        $entity->getObjectType(),
                    ),
                    'args' => [
                        'filter' => $this->filterFactory->get(
                            $entity,
                            $this,
                            $associationName,
                            $graphqlAssociation,
                        ),
                    ] + $this->paginationService->getArguments(),
                    'description' => $graphqlAssociation->description,
                    'resolve' => $this->resolveCollectionFactory->get($entity),
                ];
            };
        }

        return $fields;
    }

    /**
     * Add computed fields to the GraphQL type.  A computed field of an entity
     * type is given as a function, as an association is, so its type is
     * built when the schema needs it: the entity may be this one, or one
     * whose type has a computed field of this one's type.
     *
     * @return array<string, mixed>
     */
    protected function addComputedFields(): array
    {
        $fields = [];

        foreach ($this->entityMetadata->computedFields as $fieldName => $computedFieldMetadata) {
            if (! $this->entityTypeContainer->has($computedFieldMetadata->type)) {
                $fields[$fieldName] = [
                    'type' => self::computedFieldType(
                        $computedFieldMetadata,
                        $this->typeContainer->get($computedFieldMetadata->type),
                    ),
                    'description' => $computedFieldMetadata->description,
                ] + $this->computedFieldArgs($fieldName, $computedFieldMetadata);

                continue;
            }

            $fields[$fieldName] = function () use ($fieldName, $computedFieldMetadata): array {
                $entity = $this->entityTypeContainer->get($computedFieldMetadata->type);

                // The computed field's description, else the entity's
                return [
                    'type' => self::computedFieldType($computedFieldMetadata, $entity->getObjectType()),
                    'description' => $computedFieldMetadata->description ?? $entity->getDescription(),
                ] + $this->computedFieldArgs($fieldName, $computedFieldMetadata);
            };
        }

        return $fields;
    }

    /**
     * The args of a computed field, as a field's config gives them, or no key
     * for a computed field without arguments
     *
     * @return array{args?: array<string, array{type: Type, defaultValue?: int|float|string|bool}>}
     *
     * @throws MetadataException
     */
    private function computedFieldArgs(string $fieldName, ComputedFieldMetadata $computedFieldMetadata): array
    {
        if ($computedFieldMetadata->args === []) {
            return [];
        }

        $args = [];
        foreach ($computedFieldMetadata->args as $name => $argument) {
            $type = $this->typeContainer->get($argument->type);

            if (! $type instanceof InputType) {
                throw new MetadataException(
                    'Argument ' . $name . ' of computed field ' . $fieldName . ' of entity ' . $this->getEntityClass()
                    . ' is of type ' . $type->toString() . ', which cannot be input.',
                );
            }

            $args[$name] = ['type' => $argument->nullable ? $type : self::nonNull($type)];

            if ($argument->default === null) {
                continue;
            }

            $args[$name]['defaultValue'] = $argument->default;
        }

        return ['args' => $args];
    }

    /**
     * The type of a computed field: its type, or a list of it
     */
    private static function computedFieldType(ComputedFieldMetadata $computedFieldMetadata, Type $type): Type
    {
        return $computedFieldMetadata->list ? Type::listOf($type) : $type;
    }
}
