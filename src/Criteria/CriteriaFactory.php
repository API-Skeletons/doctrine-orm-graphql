<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\GraphQL\Criteria;

use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Criteria\Type\FiltersInputObjectType;
use ApiSkeletons\Doctrine\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\GraphQL\Type\TypeManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use League\Event\EventDispatcher;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function count;
use function in_array;

use const SORT_REGULAR;

class CriteriaFactory
{
    public function __construct(
        protected Config $config,
        protected EntityManager $entityManager,
        protected TypeManager $typeManager,
        protected EventDispatcher $eventDispatcher,
    ) {
    }

    /** @param mixed[]|null $associationMetadata */
    public function get(
        Entity $targetEntity,
        Entity|null $owningEntity = null,
        string|null $associationName = null,
        array|null $associationMetadata = null,
    ): InputObjectType {
        $typeName = $owningEntity ?
            $owningEntity->getTypeName() . '_' . $associationName . '_filter'
            : $targetEntity->getTypeName() . '_filter';

        if ($this->typeManager->has($typeName)) {
            return $this->typeManager->get($typeName);
        }

        $fields          = [];
        $entityMetadata  = $targetEntity->getMetadata();
        $excludedFilters = array_unique(
            array_merge(
                $entityMetadata['excludeCriteria'],
                $this->config->getExcludeCriteria(),
            ),
            SORT_REGULAR,
        );

        // Limit filters
        $allowedFilters = array_diff(Filters::toArray(), $excludedFilters);

        // Limit association filters
        if ($associationName) {
            $excludeCriteria = $associationMetadata['excludeCriteria'];
            $allowedFilters  = array_filter($allowedFilters, static function ($value) use ($excludeCriteria) {
                return ! in_array($value, $excludeCriteria);
            });
        }

        $this->addFields($targetEntity, $typeName, $allowedFilters, $fields);
        $this->addAssociations($targetEntity, $typeName, $allowedFilters, $fields);

        $inputObject = new InputObjectType([
            'name' => $typeName,
            'fields' => static fn () => $fields,
        ]);

        $this->typeManager->set($typeName, $inputObject);

        return $inputObject;
    }

    /**
     * @param string[]                           $allowedFilters
     * @param array<int, FiltersInputObjectType> $fields
     */
    protected function addFields(Entity $targetEntity, string $typeName, array $allowedFilters, array &$fields): void
    {
        $classMetadata  = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());
        $entityMetadata = $targetEntity->getMetadata();

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            // Only process fields that are in the graphql metadata
            if (! in_array($fieldName, array_keys($entityMetadata['fields']))) {
                continue;
            }

            $graphQLType = $this->typeManager
                ->get($entityMetadata['fields'][$fieldName]['type']);

            if ($classMetadata->isIdentifier($fieldName)) {
                $graphQLType = Type::id();
            }

            // Limit field filters
            if (
                isset($entityMetadata['fields'][$fieldName]['excludeCriteria'])
                && count($entityMetadata['fields'][$fieldName]['excludeCriteria'])
            ) {
                $fieldExcludeCriteria = $entityMetadata['fields'][$fieldName]['excludeCriteria'];
                $allowedFilters       = array_filter(
                    $allowedFilters,
                    static function ($value) use ($fieldExcludeCriteria) {
                        return ! in_array($value, $fieldExcludeCriteria);
                    },
                );
            }

            $fields[$fieldName] = [
                'name'        => $fieldName,
                'type'        => new FiltersInputObjectType($typeName, $fieldName, $graphQLType, $allowedFilters),
                'description' => 'Filters for ' . $fieldName,
            ];
        }
    }

    /**
     * @param string[]                           $allowedFilters
     * @param array<int, FiltersInputObjectType> $fields
     */
    protected function addAssociations(Entity $targetEntity, string $typeName, array $allowedFilters, array &$fields): void
    {
        $classMetadata  = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());
        $entityMetadata = $targetEntity->getMetadata();

        // Add eq filter for to-one associations
        foreach ($classMetadata->getAssociationNames() as $associationName) {
            // Only process fields which are in the graphql metadata
            if (! in_array($associationName, array_keys($entityMetadata['fields']))) {
                continue;
            }

            $associationMetadata = $classMetadata->getAssociationMapping($associationName);
            $graphQLType         = Type::id();
            switch ($associationMetadata['type']) {
                case ClassMetadataInfo::ONE_TO_ONE:
                case ClassMetadataInfo::MANY_TO_ONE:
                case ClassMetadataInfo::TO_ONE:
                    // eq filter is for association:value
                    if (in_array(Filters::EQ, $allowedFilters)) {
                        $fields[$associationName] = [
                            'name' => $associationName,
                            'type' => new FiltersInputObjectType($typeName, $associationName, $graphQLType, ['eq']),
                            'description' => 'Filters for ' . $associationName,
                        ];
                    }
            }
        }
    }
}
