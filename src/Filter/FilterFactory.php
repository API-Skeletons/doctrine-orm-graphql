<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Association;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Field;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use GraphQL\Type\Definition\InputObjectType as GraphQLInputObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use League\Event\EventDispatcher;

use function array_filter;
use function array_keys;
use function array_merge;
use function array_udiff;
use function array_unique;
use function count;
use function in_array;
use function md5;
use function serialize;
use function ucwords;

use const SORT_REGULAR;

/**
 * Build filters for an entity
 */
class FilterFactory
{
    public function __construct(
        protected Config $config,
        protected EntityManager $entityManager,
        protected TypeContainer $typeContainer,
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
            'Filter_' . $owningEntity->getTypeName() . '_' . ucwords($associationName)
            : 'Filter_' . $targetEntity->getTypeName();

        if ($this->typeContainer->has($typeName)) {
            return $this->typeContainer->get($typeName);
        }

        $entityMetadata = $targetEntity->getMetadata();

        $excludedFilters = array_unique(
            array_merge(
                Filters::fromArray($entityMetadata['excludeFilters'] ?? []),
                Filters::fromArray($this->config->getExcludeFilters()),
            ),
            SORT_REGULAR,
        );

        // Get the allowed filters
        $allowedFilters = array_udiff(Filters::cases(), $excludedFilters, static function ($a, $b) {
            return $a->value <=> $b->value;
        });

        // Limit association filters
        if ($associationName) {
            $excludeFilters = Filters::fromArray($associationMetadata['excludeFilters'] ?? []);
            $allowedFilters = array_filter($allowedFilters, static function ($value) use ($excludeFilters) {
                return ! in_array($value, $excludeFilters);
            });
        }

        $fields = $this->addFields($targetEntity, $allowedFilters);
        $fields = array_merge($fields, $this->addAssociations($targetEntity, $allowedFilters));

        $inputObject = new GraphQLInputObjectType([
            'name' => $typeName,
            'fields' => static fn () => $fields,
        ]);

        $this->typeContainer->set($typeName, $inputObject);

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
    protected function addFields(Entity $targetEntity, array $allowedFilters): array
    {
        $fields = [];

        $classMetadata  = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());
        $entityMetadata = $targetEntity->getMetadata();

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            // Only process fields that are in the graphql metadata
            if (! in_array($fieldName, array_keys($entityMetadata['fields']))) {
                continue;
            }

            $type = $this->typeContainer
                ->get($entityMetadata['fields'][$fieldName]['type']);

            // Custom types may hit this condition
            if (! $type instanceof ScalarType) {
                continue;
            }

            // Skip Blob fields
            if ($type->name() === 'Blob') {
                continue;
            }

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

            // Remove filters that are not allowed for this field type
            $filteredFilters = $this->filterFiltersByType($allowedFilters, $type);

            // ScalarType field filters are named by their field type
            // and a hash of the allowed filters
            $filterTypeName = 'Filters_' . $type->name() . '_' . md5(serialize($filteredFilters));

            if ($this->typeContainer->has($filterTypeName)) {
                $fieldType = $this->typeContainer->get($filterTypeName);
            } else {
                $fieldType = new Field($this->typeContainer, $type, $filteredFilters);
                $this->typeContainer->set($filterTypeName, $fieldType);
            }

            $alias = $targetEntity->getExtractionMap()[$fieldName] ?? null;

            $fields[$alias ?? $fieldName] = [
                'name'        => $alias ?? $fieldName,
                'type'        => $fieldType,
                'description' => $type->name() . ' Filters',
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
    protected function addAssociations(Entity $targetEntity, array $allowedFilters): array
    {
        $fields = [];

        $classMetadata  = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());
        $entityMetadata = $targetEntity->getMetadata();

        // Add eq filter for to-one associations
        foreach ($classMetadata->getAssociationNames() as $associationName) {
            // Only process fields which are in the graphql metadata
            if (! isset($entityMetadata['fields'][$associationName])) {
                continue;
            }

            $associationMetadata = $classMetadata->getAssociationMapping($associationName);

            if (
                in_array($associationMetadata['type'], [
                    ClassMetadata::TO_MANY,
                    ClassMetadata::MANY_TO_MANY,
                    ClassMetadata::ONE_TO_MANY,
                ])
                || ! in_array(Filters::EQ, $allowedFilters)
            ) {
                continue;
            }

            $filterTypeName = 'Filters_ID_' . md5(serialize($allowedFilters));

            if (! $this->typeContainer->has($filterTypeName)) {
                $this->typeContainer->set($filterTypeName, new Association($this->typeContainer, Type::id(), [Filters::EQ]));
            }

            // eq filter is for association id from parent entity
            $fields[$associationName] = [
                'name' => $associationName,
                'type' => $this->typeContainer->get($filterTypeName),
                'description' => 'Association Filters',
            ];
        }

        return $fields;
    }

    /**
     * Filter the allowed filters based on the field type
     *
     * @param Filters[] $filters
     *
     * @return Filters[]
     */
    protected function filterFiltersByType(array $filters, ScalarType $type): array
    {
        $filterCollection = new ArrayCollection($filters);

        // Numbers
        if (
            in_array($type->name(), [
                'Float',
                'ID',
                'Int',
                'Integer',
            ])
        ) {
            $filterCollection->removeElement(Filters::CONTAINS);
            $filterCollection->removeElement(Filters::STARTSWITH);
            $filterCollection->removeElement(Filters::ENDSWITH);
        } elseif ($type->name() === 'Boolean') {
            $filterCollection->removeElement(Filters::LT);
            $filterCollection->removeElement(Filters::LTE);
            $filterCollection->removeElement(Filters::GT);
            $filterCollection->removeElement(Filters::GTE);
            $filterCollection->removeElement(Filters::BETWEEN);
            $filterCollection->removeElement(Filters::CONTAINS);
            $filterCollection->removeElement(Filters::STARTSWITH);
            $filterCollection->removeElement(Filters::ENDSWITH);
        } elseif (
            in_array($type->name(), [
                'String',
                'Text',
            ])
        ) {
            $filterCollection->removeElement(Filters::LT);
            $filterCollection->removeElement(Filters::LTE);
            $filterCollection->removeElement(Filters::GT);
            $filterCollection->removeElement(Filters::GTE);
            $filterCollection->removeElement(Filters::BETWEEN);
        } elseif (
            in_array($type->name(), [
                'Date',
                'DateTime',
                'DateTimeImmutable',
                'DateTimeTZ',
                'DateTimeTZImmutable',
                'Time',
                'TimeImmutable',
            ])
        ) {
            $filterCollection->removeElement(Filters::CONTAINS);
            $filterCollection->removeElement(Filters::STARTSWITH);
            $filterCollection->removeElement(Filters::ENDSWITH);
        }

        return $filterCollection->toArray();
    }
}
