<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Buildable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use function assert;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * This type is built within the TypeContainer.  It is built with, and
 * registered by, its own name, Connection_ followed by the name of what it
 * connects; see nameFor().
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class Connection extends ObjectType implements
    Buildable
{
    private const string PREFIX = 'Connection_';

    /** @param mixed[] $params */
    public function __construct(Container $container, string $typeName, mixed $params)
    {
        assert($params[0] instanceof ObjectType);
        $objectType = $params[0];

        $connects = str_starts_with($typeName, self::PREFIX) ? substr($typeName, strlen(self::PREFIX)) : $typeName;

        $nodeType = $container->build(Node::class, 'Node_' . $connects, $objectType);
        assert($nodeType instanceof ObjectType);

        $configuration = [
            'name' => self::nameFor($connects),
            'description' => 'Connection for ' . $connects,
            'fields' => [
                'edges' => Type::listOf($nodeType),
                'totalCount' => Type::nonNull(Type::int()),
                'pageInfo' => $container->get('PageInfo'),
            ],
        ];

        parent::__construct($configuration);
    }

    /**
     * The name of the connection of a type, which it is built with
     */
    public static function nameFor(string $connects): string
    {
        return self::PREFIX . $connects;
    }
}
