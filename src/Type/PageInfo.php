<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * This type is defined in the GraphqQL Complete Connection Specification
 */
class PageInfo extends ObjectType
{
    public function __construct()
    {
        $configuration = [
            'name' => 'PageInfo',
            'description' => 'Page information',
            'fields' => [
                'startCursor' => [
                    'description' => 'Cursor corresponding to the first node in edges.',
                    'type' => Type::nonNull(Type::string()),
                ],
                'endCursor' => [
                    'description' => 'Cursor corresponding to the last node in edges.',
                    'type' => Type::nonNull(Type::string()),
                ],
                'hasPreviousPage' => [
                    'description' => 'If edges contains more than last elements return true, otherwise false.',
                    'type' => Type::nonNull(Type::boolean()),
                ],
                'hasNextPage' => [
                    'description' => 'If edges contains more than first elements return true, otherwise false.',
                    'type' => Type::nonNull(Type::boolean()),
                ],
            ],
        ];

        parent::__construct($configuration);
    }
}
