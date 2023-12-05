<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\BuildMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use League\Event\EventDispatcher;
use ReflectionClass;

use function array_map;
use function assert;
use function count;

/**
 * Build metadata for all entities
 */
class MetadataFactory extends AbstractMetadataFactory
{
    public function __construct(
        protected ArrayObject $metadata,
        protected EntityManager $entityManager,
        protected Config $config,
        protected GlobalEnable $globalEnable,
        protected EventDispatcher $eventDispatcher,
    ) {
    }

    public function __invoke(): ArrayObject
    {
        // Return cached metadata
        if (count($this->metadata)) {
            return $this->metadata;
        }

        // Build metadata for all entities
        $entityClasses = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $entityClasses[] = $metadata->getName();
        }

        // If global enable is set, use it to build metadata
        if ($this->config->getGlobalEnable()) {
            $this->metadata = ($this->globalEnable)($entityClasses);

            return $this->metadata;
        }

        // Build metadata for each entity
        foreach ($entityClasses as $entityClass) {
            $reflectionClass     = new ReflectionClass($entityClass);
            $entityClassMetadata = $this->entityManager
                ->getMetadataFactory()->getMetadataFor($reflectionClass->getName());

            $this->buildMetadataForEntity($reflectionClass);
            $this->buildMetadataForFields($entityClassMetadata, $reflectionClass);
            $this->buildMetadataForAssociations($entityClassMetadata, $reflectionClass);
        }

        // Dispatch event to allow modification of metadata
        $this->eventDispatcher->dispatch(
            new BuildMetadata($this->metadata, 'metadata.build'),
        );

        return $this->metadata;
    }

    /**
     * Using the entity class attributes, generate the metadata.
     * The buildmetadata* functions exist to simplify the buildMetadata
     * function.
     */
    private function buildMetadataForEntity(ReflectionClass $reflectionClass): void
    {
        $entityInstance = null;

        // Fetch attributes for the entity class filterd by Attribute\Entity
        foreach ($reflectionClass->getAttributes(Attribute\Entity::class) as $attribute) {
            $instance = $attribute->newInstance();

            // Only process attributes for the current Config group
            if ($instance->getGroup() !== $this->config->getGroup()) {
                continue;
            }

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
                'namingStrategy' => $instance->getNamingStrategy(),
                'fields' => [],
                'filters' => $instance->getFilters(),
                'excludeCriteria' => array_map(
                    static fn (Filters $filter) => $filter->getName(),
                    $instance->getExcludeFilters(),
                ),
                'description' => $instance->getDescription(),
                'typeName' => $instance->getTypeName()
                    ? $this->appendGroupSuffix($instance->getTypeName()) :
                    $this->getTypeName($reflectionClass->getName()),
            ];
        }
    }

    /**
     * Using the entity class attributes, generate the metadata.
     */
    private function buildMetadataForFields(
        ClassMetadata $entityClassMetadata,
        ReflectionClass $reflectionClass,
    ): void {
        foreach ($entityClassMetadata->getFieldNames() as $fieldName) {
            $fieldInstance   = null;
            $reflectionField = $reflectionClass->getProperty($fieldName);

            // Fetch attributes for the field filtered by Attribute\Field
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

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['description'] =
                    $instance->getDescription();

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['type'] =
                    $instance->getType() ?? $entityClassMetadata->getTypeOfField($fieldName);

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['excludeFilters'] =
                    array_map(
                        static fn (Filters $filter) => $filter->getName(),
                        $instance->getExcludeFilters(),
                    );

                // Set the hydrator strategy
                if ($instance->getHydratorStrategy()) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['hydratorStrategy'] =
                        $instance->getHydratorStrategy();
                } else {
                    // Set default strategy based on field type
                    $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['hydratorStrategy'] =
                        $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName));
                }
            }
        }
    }

    private function buildMetadataForAssociations(
        ClassMetadata $entityClassMetadata,
        ReflectionClass $reflectionClass,
    ): void {
        // Fetch attributes for associations
        $associationNames = $this->entityManager->getMetadataFactory()
            ->getMetadataFor($reflectionClass->getName())->getAssociationNames();

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

                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['limit'] =
                    $instance->getLimit();

                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['description'] =
                    $instance->getDescription();

                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['excludeCriteria'] =
                    array_map(
                        static fn (Filters $filter) => $filter->getName(),
                        $instance->getExcludeFilters(),
                    );

                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['filterCriteriaEventName'] =
                    $instance->getFilterCriteriaEventName();

                if ($instance->getHydratorStrategy()) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['hydratorStrategy']
                        = $instance->getHydratorStrategy();

                    // If the hydrator straegy is set, skip the following
                    continue;
                }

                // Set strategy for isOwningSide many-to-many associations
                $mapping = $entityClassMetadata->getAssociationMapping($associationName);

                // See comment on NullifyOwningAssociation for details of why this is done
                if ($mapping['type'] === ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['hydratorStrategy'] =
                        Strategy\NullifyOwningAssociation::class;
                } else {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['hydratorStrategy'] =
                        Strategy\AssociationDefault::class;
                }
            }
        }
    }
}
