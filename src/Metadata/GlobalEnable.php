<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\BuildMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use League\Event\EventDispatcher;

use function in_array;

final class GlobalEnable extends AbstractMetadataFactory
{
    private ArrayObject $metadata;

    public function __construct(
        private EntityManager $entityManager,
        protected Config $config,
        protected EventDispatcher $eventDispatcher,
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
                'namingStrategy' => null,
                'fields' => [],
                'filters' => [],
                'excludeCriteria' => [],
                'description' => $entityClass,
                'typeName' => $this->getTypeName($entityClass),
            ];

            $this->buildFieldMetadata($entityClass);
            $this->buildAssociationMetadata($entityClass);
        }

        $this->eventDispatcher->dispatch(
            new BuildMetadata($this->metadata, 'metadata.build'),
        );

        return $this->metadata;
    }

    private function buildFieldMetadata(string $entityClass): void
    {
        $entityClassMetadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);

        foreach ($entityClassMetadata->getFieldNames() as $fieldName) {
            if (in_array($fieldName, $this->config->getGlobalIgnore())) {
                continue;
            }

            $this->metadata[$entityClass]['fields'][$fieldName] = [
                'description' => $fieldName,
                'type' => $entityClassMetadata->getTypeOfField($fieldName),
                'strategy' => $this->getDefaultStrategy($entityClassMetadata->getTypeOfField($fieldName)),
                'excludeCriteria' => [],
            ];
        }
    }

    private function buildAssociationMetadata(string $entityClass): void
    {
        $entityClassMetadata = $this->entityManager->getMetadataFactory()->getMetadataFor($entityClass);

        foreach ($entityClassMetadata->getAssociationNames() as $associationName) {
            if (in_array($associationName, $this->config->getGlobalIgnore())) {
                continue;
            }

            $this->metadata[$entityClass]['fields'][$associationName] = [
                'excludeCriteria' => [],
                'description' => $associationName,
                'filterCriteriaEventName' => null,
                'strategy' => Strategy\AssociationDefault::class,
            ];
        }
    }
}
