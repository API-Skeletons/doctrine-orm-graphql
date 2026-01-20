<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Language\AST\Node;
use Throwable;

use function implode;
use function sprintf;

/**
 * Thrown when configuration is invalid
 */
class Configuration extends GraphQL
{
    /** @param string[] $validOptions */
    public function __construct(
        string $message,
        array $validOptions = [],
        Node|null $node = null,
        Throwable|null $previous = null,
    ) {
        if ($validOptions !== []) {
            $message .= sprintf("\nValid options: %s", implode(', ', $validOptions));
        }

        parent::__construct($message, $node, null, [], null, $previous);
    }
}
