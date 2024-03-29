<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\FieldResolver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Connection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ArrayObject;
use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use Laminas\Hydrator\HydratorInterface;
use League\Event\EventDispatcher;

use function array_keys;
use function assert;
use function in_array;
use function ksort;
use function ucwords;

use const SORT_REGULAR;

/**
 * This class is used to build an ObjectType for an entity
 */
class Entity
{
    /** @var mixed[]  */
    protected array $metadata;
    protected Config $config;
    protected FilterFactory $filterFactory;
    protected EntityManager $entityManager;
    protected EntityTypeContainer $entityTypeContainer;
    protected EventDispatcher $eventDispatcher;
    protected FieldResolver $fieldResolver;
    protected ObjectType|null $objectType = null;
    protected HydratorContainer $hydratorFactory;
    protected ResolveCollectionFactory $collectionFactory;
    protected TypeContainer $typeContainer;

    /** @param mixed[] $params */
    public function __construct(AbstractContainer $container, string $typeName)
    {
        assert($container instanceof Driver);

        $this->collectionFactory   = $container->get(ResolveCollectionFactory::class);
        $this->config              = $container->get(Config::class);
        $this->entityManager       = $container->get(EntityManager::class);
        $this->entityTypeContainer = $container->get(EntityTypeContainer::class);
        $this->eventDispatcher     = $container->get(EventDispatcher::class);
        $this->fieldResolver       = $container->get(FieldResolver::class);
        $this->filterFactory       = $container->get(FilterFactory::class);
        $this->hydratorFactory     = $container->get(HydratorContainer::class);
        $this->typeContainer       = $container->get(TypeContainer::class);

        if (! isset($container->get('metadata')[$typeName])) {
            throw new Error(
                'Entity ' . $typeName . ' is not mapped in the metadata',
            );
        }

        $this->metadata = $container->get('metadata')[$typeName];
    }

    public function getHydrator(): HydratorInterface
    {
        return $this->hydratorFactory->get($this->getEntityClass());
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

    public function getEntityClass(): string
    {
        return $this->metadata['entityClass'];
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

        $fields = [];

        $this->addFields($fields);
        $this->addAssociations($fields);

        /** @var ArrayObject<'description'|'fields'|'name'|'resolveField', mixed> $arrayObject */
        $arrayObject = new ArrayObject([
            'name' => $this->getTypeName(),
            'description' => $this->getDescription(),
            'fields' => static fn () => $fields,
            'resolveField' => $this->fieldResolver,
        ]);

        /**
         * Dispatch event to allow modifications to the ObjectType definition
         */
        $this->eventDispatcher->dispatch(
            new EntityDefinition($arrayObject, $this->getEntityClass() . '.definition'),
        );

        /**
         * If sortFields then resolve the fields and sort them
         */
        if ($this->config->getSortFields()) {
            if ($arrayObject['fields'] instanceof Closure) {
                $arrayObject['fields'] = $arrayObject['fields']();
            }

            ksort($arrayObject['fields'], SORT_REGULAR);
        }

        /** @psalm-suppress InvalidArgument */
        $this->objectType = new ObjectType($arrayObject->getArrayCopy());

        return $this->objectType;
    }

    /** @param array<int, mixed[]> $fields */
    protected function addFields(array &$fields): void
    {
        $classMetadata = $this->entityManager->getClassMetadata($this->getEntityClass());

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if (! in_array($fieldName, array_keys($this->metadata['fields']))) {
                continue;
            }

            $fields[$fieldName] = [
                'type' => $this->typeContainer
                    ->get($this->getmetadata()['fields'][$fieldName]['type']),
                'description' => $this->metadata['fields'][$fieldName]['description'],
            ];
        }
    }

    /** @param array<int, mixed[]> $fields */
    protected function addAssociations(array &$fields): void
    {
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
            $targetEntity             = $associationMetadata['targetEntity'];
            $fields[$associationName] = function () use ($targetEntity, $associationName) {
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
                    'resolve' => $this->collectionFactory->get($entity),
                ];
            };
        }
    }
}
