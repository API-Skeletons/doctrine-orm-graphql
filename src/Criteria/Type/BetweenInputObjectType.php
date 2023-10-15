<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Filters;
use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

class BetweenInputObjectType extends InputObjectType
{
    public function __construct(string $typeName, string $fieldName, ScalarType|ListOfType $type)
    {
        $fields = [
            'from' => new InputObjectField([
                'name'        => 'from',
                'type'        => $type,
                'description' => 'Low value of between',
            ]),
            'to' => new InputObjectField([
                'name'        => 'to',
                'type'        => $type,
                'description' => 'High value of between',
            ]),
        ];

        parent::__construct([
            'name' => $typeName
                . '_' . $fieldName
                . '_filters_'
                . Filters::BETWEEN
                . '_fields',
            'description' => 'Between `from` and `to`',
            'fields'      => static fn () => $fields,
        ]);
    }
}
