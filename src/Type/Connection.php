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
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Connection extends ObjectType implements
    Buildable
{
    /** @param mixed[] $params */
    public function __construct(Container $container, string $typeName, mixed $params)
    {
        assert($params[0] instanceof ObjectType);
        $objectType = $params[0];

        $nodeType = $container->build(Node::class, 'Node_' . $typeName, $objectType);
        assert($nodeType instanceof ObjectType);

        $configuration = [
            'name' => 'Connection_' . $typeName,
            'description' => 'Connection for ' . $typeName,
            'fields' => [
                'edges' => Type::listOf($nodeType),
                'totalCount' => Type::nonNull(Type::int()),
                'pageInfo' => $container->get('PageInfo'),
            ],
        ];

        parent::__construct($configuration);
    }
}
