<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\GraphQL\Metadata;

use ApiSkeletons\Doctrine\GraphQL\Attribute;
use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Event\BuildMetadata;
use ApiSkeletons\Doctrine\GraphQL\Hydrator\Strategy;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use League\Event\EventDispatcher;
use ReflectionClass;

use function assert;
use function count;

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
        if (count($this->metadata)) {
            return $this->metadata;
        }

        $entityClasses = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $entityClasses[] = $metadata->getName();
        }

        if ($this->config->getGlobalEnable()) {
            $this->metadata = ($this->globalEnable)($entityClasses);

            return $this->metadata;
        }

        foreach ($entityClasses as $entityClass) {
            $reflectionClass     = new ReflectionClass($entityClass);
            $entityClassMetadata = $this->entityManager
                ->getMetadataFactory()->getMetadataFor($reflectionClass->getName());

            $this->buildMetadataForEntity($reflectionClass);
            $this->buildMetadataForFields($entityClassMetadata, $reflectionClass);
            $this->buildMetadataForAssociations($entityClassMetadata, $reflectionClass);
        }

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

            // Only process attributes for the Config group
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
                'excludeCriteria' => $instance->getExcludeCriteria(),
                'description' => $instance->getDescription(),
                'typeName' => $instance->getTypeName()
                    ? $this->appendGroupSuffix($instance->getTypeName()) :
                    $this->getTypeName($reflectionClass->getName()),
            ];
        }
    }

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

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['description'] =
                    $instance->getDescription();

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['type'] =
                    $instance->getType() ?? $entityClassMetadata->getTypeOfField($fieldName);

                if ($instance->getStrategy()) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['strategy'] =
                        $instance->getStrategy();

                    continue;
                }

                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['excludeCriteria'] =
                    $instance->getExcludeCriteria();

                // Set default strategy based on field type
                $this->metadata[$reflectionClass->getName()]['fields'][$fieldName]['strategy'] =
                    $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName));
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

                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['limit']                   =
                    $instance->getLimit();
                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['description']             =
                    $instance->getDescription();
                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['excludeCriteria']         =
                    $instance->getExcludeCriteria();
                $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['filterCriteriaEventName'] =
                    $instance->getFilterCriteriaEventName();

                if ($instance->getStrategy()) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['strategy']
                        = $instance->getStrategy();

                    continue;
                }

                $mapping = $entityClassMetadata->getAssociationMapping($associationName);

                // See comment on NullifyOwningAssociation for details of why this is done
                if ($mapping['type'] === ClassMetadataInfo::MANY_TO_MANY && $mapping['isOwningSide']) {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['strategy'] =
                        Strategy\NullifyOwningAssociation::class;
                } else {
                    $this->metadata[$reflectionClass->getName()]['fields'][$associationName]['strategy'] =
                        Strategy\AssociationDefault::class;
                }
            }
        }
    }
}
