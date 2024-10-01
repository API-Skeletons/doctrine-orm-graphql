<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Buildable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use function assert;

/**
 * This type is built within the TypeContainer
 */
class Node extends ObjectType implements
    Buildable
{
    /** @param mixed[] $params */
    public function __construct(Container $container, string $typeName, array $params)
    {
        assert($container instanceof TypeContainer);
        assert($params[0] instanceof ObjectType);

        $configuration = [
            'name' => $typeName,
            'fields' => [
                'node' => $params[0],
                'cursor' => Type::nonNull(Type::string()),
            ],
        ];

        parent::__construct($configuration);
    }
}
