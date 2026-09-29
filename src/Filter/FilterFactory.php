<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Association;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Field;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\AssociationMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use GraphQL\Type\Definition\InputObjectType as GraphQLInputObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use League\Event\EventDispatcher;
use ReflectionClass;

use function array_filter;
use function array_merge;
use function array_udiff;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function in_array;
use function ucwords;

use const SORT_REGULAR;

/**
 * Build filters for an entity
 */
final class FilterFactory
{
    public function __construct(
        protected readonly Config $config,
        protected readonly EntityManager $entityManager,
        protected readonly TypeContainer $typeContainer,
        protected readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * Return an InputObjectType of filters for the target entity.  For a
     * collection, the owning entity, association name and the association's
     * metadata are given.
     */
    public function get(
        Entity $targetEntity,
        Entity|null $owningEntity = null,
        string|null $associationName = null,
        AssociationMetadata|null $associationMetadata = null,
    ): GraphQLInputObjectType {
        $typeName = $owningEntity ?
            'Filter_' . $owningEntity->getTypeName() . '_' . ucwords((string) $associationName)
            : 'Filter_' . $targetEntity->getTypeName();

        if ($this->typeContainer->has($typeName)) {
            $filter = $this->typeContainer->get($typeName);
            assert($filter instanceof GraphQLInputObjectType);

            return $filter;
        }

        $excludedFilters = array_unique(
            array_merge(
                Filters::fromArray($targetEntity->getEntityMetadata()->excludeFilters),
                Filters::fromArray($this->config->getExcludeFilters()),
            ),
            SORT_REGULAR,
        );

        // Get the allowed filters
        /** @psalm-suppress MixedPropertyFetch */
        $allowedFilters = array_udiff(Filters::cases(), $excludedFilters, static function ($a, $b) {
            return $a->value <=> $b->value;
        });

        // Limit association filters
        if ($associationMetadata !== null) {
            $excludeFilters = Filters::fromArray($associationMetadata->excludeFilters);
            $allowedFilters = array_filter($allowedFilters, static function ($value) use ($excludeFilters) {
                return ! in_array($value, $excludeFilters);
            });
        }

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $fields = $this->addFields($targetEntity, $allowedFilters);
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $fields = array_merge($fields, $this->addAssociations($targetEntity, $allowedFilters));

        $inputObject = (new ReflectionClass(GraphQLInputObjectType::class))
            ->newLazyGhost(static function (GraphQLInputObjectType $object) use ($typeName, $fields): void {
                /** @psalm-suppress PossiblyInvalidArgument */
                /** @psalm-suppress DirectConstructorCall */
                $object->__construct([ // @phpstan-ignore argument.type
                    'name' => $typeName,
                    'fields' => static fn () => $fields,
                ]);
            });

        $this->typeContainer->set($typeName, $inputObject);

        return $inputObject;
    }

    /**
     * Add each field filters
     *
     * @param Filters[] $allowedFilters
     *
     * @return array<string, mixed[]>
     */
    protected function addFields(Entity $targetEntity, array $allowedFilters): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            // Only process fields that are in the graphql metadata
            $fieldMetadata = $targetEntity->getEntityMetadata()->fields[$fieldName] ?? null;
            if ($fieldMetadata === null) {
                continue;
            }

            $type = $this->typeContainer->get($fieldMetadata->type);

            // Custom types may hit this condition
            if (! $type instanceof ScalarType) {
                continue;
            }

            // Skip Blob fields
            if ($type->name() === 'Blob') {
                continue;
            }

            // Limit field filters.  These apply to this field only, so they
            // are removed from a copy of the entity's allowed filters.
            $fieldAllowedFilters = $allowedFilters;
            if (count($fieldMetadata->excludeFilters)) {
                $fieldExcludeFilters = Filters::fromArray($fieldMetadata->excludeFilters);
                $fieldAllowedFilters = array_filter(
                    $allowedFilters,
                    static function ($value) use ($fieldExcludeFilters) {
                        return ! in_array($value, $fieldExcludeFilters);
                    },
                );
            }

            // Remove filters that are not allowed for this field type
            $filteredFilters = $this->filterFiltersByType($fieldAllowedFilters, $type, $fieldMetadata->type);

            // An input object must have a field
            if (! $filteredFilters) {
                continue;
            }

            $fieldType = $this->getFieldFilterType($type, $filteredFilters);

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
     * @param Filters[] $allowedFilters
     *
     * @return array<string, mixed[]>
     */
    protected function addAssociations(Entity $targetEntity, array $allowedFilters): array
    {
        $fields = [];

        $classMetadata = $this->entityManager->getClassMetadata($targetEntity->getEntityClass());

        // A to-one association is filtered by the identifier of its target
        $associationFilters = array_values(array_filter(
            [Filters::EQ, Filters::NEQ, Filters::IN, Filters::NOTIN, Filters::ISNULL],
            static fn (Filters $filter): bool => in_array($filter, $allowedFilters, true),
        ));

        foreach ($classMetadata->getAssociationNames() as $associationName) {
            // Only process associations which are in the graphql metadata
            if (! isset($targetEntity->getEntityMetadata()->associations[$associationName])) {
                continue;
            }

            if (! $associationFilters || ! $classMetadata->isSingleValuedAssociation($associationName)) {
                continue;
            }

            // A composite identifier is not one value
            $targetClassMetadata = $this->entityManager->getClassMetadata(
                $classMetadata->getAssociationTargetClass($associationName),
            );
            if (count($targetClassMetadata->getIdentifierFieldNames()) !== 1) {
                continue;
            }

            $filterType = $this->getFieldFilterType(Type::id(), $associationFilters, true);

            // An aliased association is filtered by its alias, as its field is
            // named
            $alias = $targetEntity->getExtractionMap()[$associationName] ?? null;

            $fields[$alias ?? $associationName] = [
                'name' => $alias ?? $associationName,
                'type' => $filterType,
                'description' => 'Association Filters',
            ];
        }

        return $fields;
    }

    /**
     * The filter type of a field or association, shared by every field of the
     * same type and filters.  It is named by a short hash of the filters; a
     * registered type of the same name must have the same filters.
     *
     * @param Filters[] $filters
     *
     * @throws FilterException
     */
    private function getFieldFilterType(ScalarType $type, array $filters, bool $association = false): Field
    {
        $filters = array_values($filters);
        $name    = Field::nameFor($type, $filters);

        if (! $this->typeContainer->has($name)) {
            $this->typeContainer->set(
                $name,
                $association
                    ? new Association($this->typeContainer, $type, $filters)
                    : new Field($this->typeContainer, $type, $filters),
            );
        }

        $filterType = $this->typeContainer->get($name);

        if (! $filterType instanceof Field || $filterType->allowedFilters !== $filters) {
            throw new FilterException(
                'Filter type name ' . $name . ' is already used for different filters.',
            );
        }

        return $filterType;
    }

    /**
     * Filter the allowed filters based on the field type
     *
     * @param Filters[] $filters
     * @param string    $fieldType The field's type in the metadata
     *
     * @return Filters[]
     */
    protected function filterFiltersByType(array $filters, ScalarType $type, string|null $fieldType = null): array
    {
        $filterCollection = new ArrayCollection($filters);

        // Numbers.  A bigint, decimal or number is a String, which keeps its
        // precision, but it is a number.
        if (
            in_array($type->name(), [
                'Float',
                'ID',
                'Int',
                'Integer',
            ])
            || in_array($fieldType, ['bigint', 'decimal', 'number'], true)
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
                'DateImmutable',
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
        } elseif ($type->name() === 'DateInterval') {
            // Stored as a string, which does not order as the duration does
            $filterCollection->removeElement(Filters::LT);
            $filterCollection->removeElement(Filters::LTE);
            $filterCollection->removeElement(Filters::GT);
            $filterCollection->removeElement(Filters::GTE);
            $filterCollection->removeElement(Filters::BETWEEN);
            $filterCollection->removeElement(Filters::CONTAINS);
            $filterCollection->removeElement(Filters::STARTSWITH);
            $filterCollection->removeElement(Filters::ENDSWITH);
            $filterCollection->removeElement(Filters::SORT);
            $filterCollection->removeElement(Filters::SORTPRIORITY);
        } elseif ($type->name() === 'Json') {
            // A filter value is decoded JSON, which does not compare to the
            // stored JSON text, so only isnull applies
            $filterCollection = new ArrayCollection(in_array(Filters::ISNULL, $filters, true) ? [Filters::ISNULL] : []);
        }

        return $filterCollection->toArray();
    }
}
