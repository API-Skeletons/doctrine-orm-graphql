<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ScalarType;

/**
 * This Type is a special case filter that takes two arguments
 */
final class Between extends InputObjectType
{
    public function __construct(readonly ScalarType $type)
    {
        parent::__construct([
            'name' => 'Between_' . $type->name(),
            'description' => 'Between `from` and `to`',
            'fields' =>  [
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
            ],
        ]);
    }
}
