<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Language\AST\Node;
use Throwable;

/**
 * Thrown when filter operations fail or are invalid
 */
final class Filter extends GraphQL
{
    public function __construct(
        string $message,
        Node|null $node = null,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $node, null, [], null, $previous);
    }
}
