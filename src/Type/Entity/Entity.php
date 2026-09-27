<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
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

    /** @param array<string, mixed> $metadata */
    public function __construct(
        private string|null $eventName,
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
    }

    /** @psalm-suppress MixedReturnStatement, MixedInferredReturnType */
    public function getHydrator(): HydratorInterface
    {
        return $this->hydratorContainer->get($this->getEntityClass());
    }

    /** @psalm-suppress MixedReturnStatement */
    public function getTypeName(): string
    {
        return $this->metadata['typeName'];
    }

    /** @psalm-suppress MixedReturnStatement */
    public function getDescription(): string|null
    {
        return $this->metadata['description'];
    }

    /** @return mixed[] */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return class-string
     *
     * @psalm-suppress MixedReturnStatement
     */
    public function getEntityClass(): string
    {
        return $this->metadata['entityClass'];
    }

    /**
     * An extraction map is used to alias fields and associations using a
     * naming strategy in the hydrator
     *
     * @return array<string, string>
     *
     * @psalm-suppress MixedReturnTypeCoercion, MixedAssignment, MixedArrayAccess, MixedOperand, MixedArrayOffset, MixedPropertyTypeCoercion
     */
    public function getExtractionMap(): array
    {
        if ($this->extractionMap !== null) {
            return $this->extractionMap;
        }

        // Build into a local so a duplicate alias does not leave a partial map
        $extractionMap = [];

        foreach ($this->metadata['fields'] as $fieldName => $fieldMetadata) {
            if (! isset($fieldMetadata['alias'])) {
                continue;
            }

            // Don't allow duplicate aliases
            if (in_array($fieldMetadata['alias'], $extractionMap)) {
                throw new MetadataException(
                    'Duplicate alias "' . $fieldMetadata['alias'] . '" found for field ' . $fieldName .
                    ' in entity ' . $this->getEntityClass() . '. Each field alias must be unique.',
                );
            }

            $extractionMap[$fieldName] = $fieldMetadata['alias'];
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
            new EntityDefinition($definition, $this->eventName ??= $this->getEntityClass() . '.definition'),
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

        /** @psalm-suppress InvalidArgument, ArgumentTypeCoercion */
        $this->objectType = (new ReflectionClass(ObjectType::class))
            ->newLazyGhost(static function (ObjectType $object) use ($definition): void {
                /** @psalm-suppress DirectConstructorCall */
                $object->__construct($definition->getArrayCopy()); // @phpstan-ignore argument.type
            });

        return $this->objectType;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedArgument, MixedArrayAccess, MixedArrayOffset
     */
    protected function addFields(): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if (! isset($this->metadata['fields'][$fieldName])) {
                continue;
            }

            $fields[$this->getExtractionMap()[$fieldName] ?? $fieldName] = [
                'type' => $this->typeContainer
                    ->get($this->getmetadata()['fields'][$fieldName]['type']),
                'description' => $this->metadata['fields'][$fieldName]['description'],
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-suppress MixedArgument, MixedArrayAccess, MixedAssignment, MixedMethodCall
     */
    protected function addAssociations(): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getAssociationNames() as $associationName) {
            if (! isset($this->metadata['fields'][$associationName])) {
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
                $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $associationName): array {
                    /** @psalm-suppress MixedArgument, MixedAssignment, MixedMethodCall */
                    $entity = $this->entityTypeContainer->get($targetEntity);

                    // The association's description, else the target entity's
                    return [
                        'type' => $entity->getObjectType(),
                        'description' => $this->metadata['fields'][$associationName]['description']
                            ?? $entity->getDescription(),
                    ];
                };

                continue;
            }

            // Collections
            $targetEntity = $associationMetadata['targetEntity'];

            $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $associationName): array {
                /** @psalm-suppress MixedArgument, MixedAssignment, MixedMethodCall, MixedArrayAccess */
                $entity    = $this->entityTypeContainer->get($targetEntity);
                $shortName = $this->getTypeName() . '_' . ucwords($associationName);

                return [
                    'type' => $this->typeContainer->build(
                        Connection::class,
                        $shortName,
                        $entity->getObjectType(),
                    ),
                    'args' => [
                        'filter' => $this->filterFactory->get(
                            $entity,
                            $this,
                            $associationName,
                            $this->metadata['fields'][$associationName],
                        ),
                    ] + $this->paginationService->getArguments(),
                    'description' => $this->metadata['fields'][$associationName]['description'],
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
     *
     * @psalm-suppress MixedAssignment, MixedArrayAccess, MixedArrayOffset, MixedArgument, MixedReturnTypeCoercion
     */
    protected function addComputedFields(): array
    {
        $fields = [];

        // Check if computed fields exist in metadata
        if (! isset($this->metadata['computedFields'])) {
            return $fields;
        }

        foreach ($this->metadata['computedFields'] as $fieldName => $computedFieldMetadata) {
            $fields[$fieldName] = [
                'type' => $this->typeContainer->get($computedFieldMetadata['type']),
                'description' => $computedFieldMetadata['description'],
            ];
        }

        return $fields;
    }
}
