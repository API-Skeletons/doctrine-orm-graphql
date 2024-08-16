<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\Common\MetadataFactory;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use League\Event\EventDispatcher;

use function in_array;

/**
 * Build metadata for all entities
 */
final class GlobalEnable extends MetadataFactory
{
    private ArrayObject $metadata;

    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly Config $config,
        protected readonly EventDispatcher $eventDispatcher,
    ) {
        $this->metadata = new ArrayObject();
    }

    /** @param string[] $entityClasses */
    public function __invoke(array $entityClasses): ArrayObject
    {
        foreach ($entityClasses as $entityClass) {
            // Get extract by value or reference
            $byValue = $this->config->getGlobalByValue() ?? true;

            // Save entity-level metadata
            $this->metadata[$entityClass] = [
                'entityClass' => $entityClass,
                'byValue' => $byValue,
                'limit' => 0,
                'fields' => [],
                'excludeFilters' => [],
                'description' => $entityClass,
                'typeName' => $this->getTypeName($entityClass),
            ];

            $this->buildFieldMetadata($entityClass);
            $this->buildAssociationMetadata($entityClass);
        }

        $this->eventDispatcher->dispatch(
            new Metadata($this->metadata, 'metadata.build'),
        );

        return $this->metadata;
    }

    private function buildFieldMetadata(string $entityClass): void
    {
        $entityClassMetadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);

        foreach ($entityClassMetadata->getFieldNames() as $fieldName) {
            if (in_array($fieldName, $this->config->getIgnoreFields())) {
                continue;
            }

            $this->metadata[$entityClass]['fields'][$fieldName] = [
                'description' => $fieldName,
                'type' => $entityClassMetadata->getTypeOfField($fieldName),
                'hydratorStrategy' => $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName)),
                'excludeFilters' => [],
            ];
        }
    }

    private function buildAssociationMetadata(string $entityClass): void
    {
        $entityClassMetadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);

        foreach ($entityClassMetadata->getAssociationNames() as $associationName) {
            if (in_array($associationName, $this->config->getIgnoreFields())) {
                continue;
            }

            $this->metadata[$entityClass]['fields'][$associationName] = [
                'limit' => null,
                'excludeFilters' => [],
                'description' => $associationName,
                'criteriaEventName' => null,
                'hydratorStrategy' => Strategy\AssociationDefault::class,
            ];
        }
    }
}
