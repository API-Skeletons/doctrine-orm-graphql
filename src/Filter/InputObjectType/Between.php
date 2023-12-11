<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType;

use GraphQL\Type\Definition\InputObjectField;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ScalarType;

use function uniqid;

/**
 * This Type is a special case filter that takes two arguments
 */
class Between extends InputObjectType
{
    public function __construct(ScalarType|ListOfType $type)
    {
        parent::__construct([
            'name' => 'Between_' . uniqid(),
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
