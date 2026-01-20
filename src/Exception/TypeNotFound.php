<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Language\AST\Node;
use Throwable;

use function implode;
use function sprintf;

/**
 * Thrown when a requested GraphQL type or entity type is not registered
 */
class TypeNotFound extends GraphQL
{
    /** @param string[] $availableTypes */
    public function __construct(
        string $typeId,
        array $availableTypes = [],
        string|null $suggestion = null,
        Node|null $node = null,
        Throwable|null $previous = null,
    ) {
        $message = sprintf('Type "%s" is not registered', $typeId);

        if ($suggestion !== null) {
            $message .= sprintf('. Did you mean "%s"?', $suggestion);
        }

        if ($availableTypes !== []) {
            $message .= sprintf("\nAvailable types: %s", implode(', ', $availableTypes));
        }

        parent::__construct($message, $node, null, [], null, $previous);
    }
}
