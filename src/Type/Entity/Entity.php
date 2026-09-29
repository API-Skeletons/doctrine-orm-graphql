<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
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
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use GraphQL\Type\Definition\ObjectType;
use Laminas\Hydrator\HydratorInterface;
use League\Event\EventDispatcher;
use ReflectionClass;

use function array_merge;
use function assert;
use function in_array;
use function is_string;
use function ksort;
use function preg_replace;
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

            $fields[$this->getExtractionMap()[$fieldName] ?? $fieldName] = [
                'type' => $this->typeContainer->get($fieldMetadata->type),
                'description' => $fieldMetadata->description,
            ];
        }

        return $fields;
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

                // The hydrator extracts an aliased association under its alias
                $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $graphqlAssociation): array {
                    $entity = $this->entityTypeContainer->get($targetEntity);

                    // The association's description, else the target entity's
                    return [
                        'type' => $entity->getObjectType(),
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
     * Add computed fields to the GraphQL type
     *
     * @return array<string, mixed>
     */
    protected function addComputedFields(): array
    {
        $fields = [];

        foreach ($this->entityMetadata->computedFields as $fieldName => $computedFieldMetadata) {
            $fields[$fieldName] = [
                'type' => $this->typeContainer->get($computedFieldMetadata->type),
                'description' => $computedFieldMetadata->description,
            ];
        }

        return $fields;
    }
}
