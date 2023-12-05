<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use GraphQL\Type\Definition\InputObjectType as GraphQLInputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

class InputObjectType extends GraphQLInputObjectType
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
