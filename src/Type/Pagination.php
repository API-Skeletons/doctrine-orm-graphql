<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class Pagination extends InputObjectType
{
    public function __construct()
    {
        $configuration = [
            'name' => 'Pagination',
            'description' => 'Pagination fields for the GraphQL Complete Connection Model',
            'fields' => [
                'first' => [
                    'type'        => Type::int(),
                    'description' => 'Takes a non-negative integer.',
                ],
                'after' => [
                    'type'        => Type::string(),
                    'description' => 'Takes the cursor type.',
                ],
                'last' => [
                    'type'        => Type::int(),
                    'description' => 'Takes a non-negative integer.',
                ],
                'before' => [
                    'type'        => Type::string(),
                    'description' => 'Takes the cursor type.',
                ],
            ],
        ];

        parent::__construct($configuration);
    }
}
