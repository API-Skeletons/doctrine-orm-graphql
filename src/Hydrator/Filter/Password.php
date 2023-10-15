<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Filter;

use Laminas\Hydrator\Filter\FilterInterface;

use function in_array;

class Password implements
    FilterInterface
{
    public function filter(string $property, object|null $instance = null): bool
    {
        $excludeFields = [
            'password',
            'secret',
        ];

        return ! in_array($property, $excludeFields);
    }
}
