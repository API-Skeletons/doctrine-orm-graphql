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
            /**
             * Because you may create multiple drivers and assign types to the
             * schema from each, there would be a name collision if this object
             * used only the name `PageInfo` as defined in section 2.1.2 of the
             * GraphQL spec:ification.
             * https://relay.dev/graphql/connections.htm#sec-Connection-Types.Fields.PageInfo
             */
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
