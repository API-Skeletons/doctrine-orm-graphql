<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
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

use function array_keys;
use function array_merge;
use function count;
use function in_array;
use function ksort;
use function ucwords;

/**
 * This class is used to build an ObjectType for an entity
 */
class Entity
{
    /** @var array<string, string> */
    protected array $extractionMap        = [];
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
        protected readonly ResolveCollectionFactory $resolveCollectionFactory,
        protected readonly TypeContainer $typeContainer,
        protected readonly array $metadata,
    ) {
    }

    public function getHydrator(): HydratorInterface
    {
        return $this->hydratorContainer->get($this->getEntityClass());
    }

    public function getTypeName(): string
    {
        return $this->metadata['typeName'];
    }

    public function getDescription(): string|null
    {
        return $this->metadata['description'];
    }

    /** @return mixed[] */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /** @return class-string */
    public function getEntityClass(): string
    {
        return $this->metadata['entityClass'];
    }

    /**
     * An extraction map is used to alias fields and associations using a
     * naming strategy in the hydrator
     *
     * @return array<string, string>
     */
    public function getExtractionMap(): array
    {
        if (count($this->extractionMap)) {
            return $this->extractionMap;
        }

        foreach ($this->metadata['fields'] as $fieldName => $fieldMetadata) {
            if (! isset($fieldMetadata['alias'])) {
                continue;
            }

            // Don't allow duplicate aliases
            if (in_array($fieldMetadata['alias'], $this->extractionMap)) {
                throw new MetadataException(
                    'Duplicate alias "' . $fieldMetadata['alias'] . '" found for field ' . $fieldName .
                    ' in entity ' . $this->getEntityClass() . '. Each field alias must be unique.',
                );
            }

            $this->extractionMap[$fieldName] = $fieldMetadata['alias'];
        }

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

        $typeName = $this->getTypeName();
        if ($this->eventName) {
            $typeName .= '.' . $this->eventName;
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
                $definition['fields'] = $definition['fields']();
            }

            ksort($definition['fields']);
        }

        /** @psalm-suppress InvalidArgument, ArgumentTypeCoercion */
        $this->objectType = (new ReflectionClass(ObjectType::class))
            ->newLazyGhost(static function (ObjectType $object) use ($definition): void {
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
            if (! in_array($fieldName, array_keys($this->metadata['fields']))) {
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

    /** @return array<string, mixed> */
    protected function addAssociations(): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getAssociationNames() as $associationName) {
            if (! in_array($associationName, array_keys($this->metadata['fields']))) {
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
                $targetEntity             = $associationMetadata['targetEntity'];
                $fields[$associationName] = function () use ($targetEntity) {
                    $entity = $this->entityTypeContainer->get($targetEntity);

                    return [
                        'type' => $entity->getObjectType(),
                        'description' => $entity->getDescription(),
                    ];
                };

                continue;
            }

            // Collections
            $targetEntity = $associationMetadata['targetEntity'];

            $fields[$this->getExtractionMap()[$associationName] ?? $associationName] = function () use ($targetEntity, $associationName) {
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
                        'pagination' => $this->typeContainer->get('pagination'),
                    ],
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
