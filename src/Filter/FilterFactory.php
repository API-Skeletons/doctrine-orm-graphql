<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Association;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Field;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use GraphQL\Type\Definition\InputObjectType as GraphQLInputObjectType;
use GraphQL\Type\Definition\Type;
use League\Event\EventDispatcher;

use function array_filter;
use function array_keys;
use function array_merge;
use function array_unique;
use function count;
use function in_array;

use const SORT_REGULAR;

/**
 * Build filters for an entity
 */
class FilterFactory
{
    public function __construct(
        protected Config $config,
        protected EntityManager $entityManager,
        protected TypeManager $typeManager,
        protected EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * Return an InputObjectType of filters for the target entity
     *
     * @param mixed[]|null $associationMetadata
     */
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

        $entityMetadata = $targetEntity->getMetadata();

        $excludedFilters = array_unique(
            array_merge(
                Filters::fromArray($entityMetadata['excludeFilters'] ?? []),
                Filters::fromArray($this->config->getExcludeFilters()),
            ),
            SORT_REGULAR,
        );

        // Get a diff of the allowed filters and the excluded filters
        // array_diff does not work on enum
        foreach (Filters::cases() as $filter) {
            if (in_array($filter, $excludedFilters)) {
                continue;
            }

            $allowedFilters[] = $filter;
        }

        // Limit association filters
        if ($associationName) {
            $excludeFilters = Filters::fromArray($associationMetadata['excludeFilters'] ?? []);
            $allowedFilters = array_filter($allowedFilters, static function ($value) use ($excludeFilters) {
                return ! in_array($value, $excludeFilters);
            });
        }

        $fields = $this->addFields($targetEntity, $typeName, $allowedFilters);
        $fields = array_merge($fields, $this->addAssociations($targetEntity, $typeName, $allowedFilters));

        $inputObject = new GraphQLInputObjectType([
            'name' => $typeName,
            'fields' => static fn () => $fields,
        ]);

        $this->typeManager->set($typeName, $inputObject);

        return $inputObject;
    }

    /**
     * Add each field filters
     *
     * @param Filters[]                          $allowedFilters
     * @param array<int, GraphQLInputObjectType> $fields
     *
     * @return array<string, mixed[]>
     */
    protected function addFields(Entity $targetEntity, string $typeName, array $allowedFilters): array
    {
        $fields = [];

        $classMetadata  = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());
        $entityMetadata = $targetEntity->getMetadata();

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            // Only process fields that are in the graphql metadata
            if (! in_array($fieldName, array_keys($entityMetadata['fields']))) {
                continue;
            }

            $graphQLType = $this->typeManager
                ->get($entityMetadata['fields'][$fieldName]['type']);

            // Limit field filters
            if (
                isset($entityMetadata['fields'][$fieldName]['excludeFilters'])
                && count($entityMetadata['fields'][$fieldName]['excludeFilters'])
            ) {
                $fieldExcludeFilters = Filters::fromArray($entityMetadata['fields'][$fieldName]['excludeFilters']);
                $allowedFilters      = array_filter(
                    $allowedFilters,
                    static function ($value) use ($fieldExcludeFilters) {
                        return ! in_array($value, $fieldExcludeFilters);
                    },
                );
            }

            $fields[$fieldName] = [
                'name'        => $fieldName,
                'type'        => new Field($typeName, $fieldName, $graphQLType, $allowedFilters),
                'description' => 'Filters for ' . $fieldName,
            ];
        }

        return $fields;
    }

    /**
     * Some relationships have an `eq` filter for the id
     *
     * @param Filters[]                          $allowedFilters
     * @param array<int, GraphQLInputObjectType> $fields
     *
     * @return array<string, mixed[]>
     */
    protected function addAssociations(Entity $targetEntity, string $typeName, array $allowedFilters): array
    {
        $fields = [];

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
                            'type' => new Association($typeName, $associationName, $graphQLType, [Filters::EQ]),
                            'description' => 'Filters for ' . $associationName,
                        ];
                    }
            }
        }

        return $fields;
    }
}
