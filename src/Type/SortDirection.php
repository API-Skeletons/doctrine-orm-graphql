<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Type\Definition\EnumType;

/**
 * The direction of the sort filter.  An enum rejects any other value during
 * GraphQL validation, before it can reach the query.
 */
final class SortDirection extends EnumType
{
    public function __construct()
    {
        parent::__construct([
            'name' => 'SortDirection',
            'description' => 'Sort direction',
            'values' => [
                'ASC' => [
                    'value' => 'ASC',
                    'description' => 'Ascending',
                ],
                'DESC' => [
                    'value' => 'DESC',
                    'description' => 'Descending',
                ],
            ],
        ]);
    }
}
