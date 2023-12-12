<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

/**
 * This class is used to create an InputObjectType of filters for a field
 * or association.  The generic term field is used for both here.
 */
class Field extends InputObjectType
{
    /** @param Filters[] $allowedFilters */
    public function __construct(
        string $typeName,
        string $fieldName,
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
        }

        parent::__construct([
            'name' => $typeName . '_' . $fieldName . '_filters',
            'description' => 'Field filters',
            'fields' => static fn () => $fields,
        ]);
    }
}
