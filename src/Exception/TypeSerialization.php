<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Language\AST\Node;
use Throwable;

/**
 * Thrown when a GraphQL scalar type fails to parse or serialize a value
 */
class TypeSerialization extends GraphQL
{
    public function __construct(
        string $message,
        Node|null $node = null,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $node, null, [], null, $previous);
    }
}
