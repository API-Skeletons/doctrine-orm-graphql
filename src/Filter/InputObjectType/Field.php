<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

use function md5;
use function serialize;
use function uniqid;

/**
 * This class is used to create an InputObjectType of filters for a field
 * or association.  The generic term field is used for both here.
 */
class Field extends InputObjectType
{
    /** @param Filters[] $allowedFilters */
    public function __construct(
        TypeContainer $typeContainer,
        ScalarType|ListOfType $type,
        array $allowedFilters,
    ) {
        /** @var array<string, array> $fields */
        $fields = [];

        foreach ($allowedFilters as $filter) {
            $fields[$filter->value] = [
                'name'        => $filter->value,
                'type'        => $filter->type($type),
                'description' => $filter->description(),
            ];

            // Custom types may hit this condition
            // @codeCoverageIgnoreStart
            if (! $type instanceof ScalarType) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            // Between is a special case filter.
            // To avoid creating a new Between type for each field,
            // check if the Between type exists and reuse it.
            if (! $fields[$filter->value]['type'] instanceof Between) {
                continue;
            }

            if (! $typeContainer->has('Between_' . $type->name())) {
                $typeContainer->set('Between_' . $type->name(), new Between($type));
            }

            $fields[$filter->value]['type'] = $typeContainer->get('Between_' . $type->name());
        }

        $typeName = $type instanceof ScalarType ? $type->name() : uniqid();

        // ScalarType field filters are named by their field type
        // and a hash of the allowed filters
        parent::__construct([
            'name' => 'Filters_' . $typeName . '_' . md5(serialize($allowedFilters)),
            'description' => 'Field filters',
            'fields' => static fn () => $fields,
        ]);
    }
}
