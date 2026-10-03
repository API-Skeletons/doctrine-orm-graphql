<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * This type is defined in the GraphQL Complete Connection Specification
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class PageInfo extends ObjectType
{
    public function __construct()
    {
        $configuration = [
            'name' => 'PageInfo',
            'description' => 'Page information',
            'fields' => [
                'startCursor' => [
                    'description' => 'The cursor of the first edge, or null when there are no edges.',
                    'type' => Type::string(),
                ],
                'endCursor' => [
                    'description' => 'The cursor of the last edge, or null when there are no edges.',
                    'type' => Type::string(),
                ],
                'hasPreviousPage' => [
                    'description' => 'True when this page does not start at the first row.',
                    'type' => Type::nonNull(Type::boolean()),
                ],
                'hasNextPage' => [
                    'description' => 'Whether rows come after this page.',
                    'type' => Type::nonNull(Type::boolean()),
                ],
            ],
        ];

        parent::__construct($configuration);
    }
}
