<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Buildable;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use function assert;

/**
 * This type is built within the TypeContainer
 */
class Connection extends ObjectType implements
    Buildable
{
    /** @param mixed[] $params */
    public function __construct(AbstractContainer $container, string $typeName, array $params)
    {
        assert($params[0] instanceof ObjectType);
        $objectType = $params[0];

        $configuration = [
            'name' => 'Connection_' . $typeName,
            'description' => 'Connection for ' . $typeName,
            'fields' => [
                'edges' => Type::listOf($container
                    ->build(Node::class, 'Node_' . $typeName, $objectType)),
                'totalCount' => Type::nonNull(Type::int()),
                'pageInfo' => $container->get('PageInfo'),
            ],
        ];

        parent::__construct($configuration);
    }
}
