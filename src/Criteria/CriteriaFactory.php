<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Criteria;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use GraphQL\Type\Definition\InputObjectType as GraphQLInputObjectType;
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
    ): GraphQLInputObjectType {
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

        // Convert the enum Filters to an array



        // Limit filters
        $allowedFilters = array_diff(Filters::valueArray(), $excludedFilters);

        // Limit association filters
        if ($associationName) {
            $excludeCriteria = $associationMetadata['excludeCriteria'];
            $allowedFilters  = array_filter($allowedFilters, static function ($value) use ($excludeCriteria) {
                return ! in_array($value, $excludeCriteria);
            });
        }

        $this->addFields($targetEntity, $typeName, $allowedFilters, $fields);
        $this->addAssociations($targetEntity, $typeName, $allowedFilters, $fields);

        $inputObject = new GraphQLInputObjectType([
            'name' => $typeName,
            'fields' => static fn () => $fields,
        ]);

        $this->typeManager->set($typeName, $inputObject);

        return $inputObject;
    }

    /**
     * @param Filters[]                          $allowedFilters
     * @param array<int, GraphQLInputObjectType> $fields
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
                'type'        => new InputObjectType($typeName, $fieldName, $graphQLType, $allowedFilters),
                'description' => 'Filters for ' . $fieldName,
            ];
        }
    }

    /**
     * @param string[]                           $allowedFilters
     * @param array<int, GraphQLInputObjectType> $fields
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
                            'type' => new InputObjectType($typeName, $associationName, $graphQLType, ['eq']),
                            'description' => 'Filters for ' . $associationName,
                        ];
                    }
            }
        }
    }
}
