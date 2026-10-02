<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Error\Error;
use Override;

/**
 * Base exception for all Doctrine ORM GraphQL exceptions
 *
 * Extends GraphQL\Error\Error to ensure compatibility with webonyx/graphql-php
 * error handling and reporting.
 *
 * An error of the library's configuration or of the entities is the
 * developer's to fix, and its message may name classes, methods and groups.
 * It is not client safe, so a client sees only webonyx's "Internal server
 * error".  An error a client's request causes is a ClientError.
 */
class GraphQL extends Error
{
    #[Override]
    public function isClientSafe(): bool
    {
        return false;
    }
}
