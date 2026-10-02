<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Exception;

use GraphQL\Error\ClientAware;
use Override;

/**
 * An error caused by a client's request, such as an invalid filter, cursor
 * or scalar value, whose message is shown to the client.  It names only the
 * GraphQL fields and the values the client sent.
 *
 * As for any webonyx error, one thrown because of another exception which is
 * not client safe is not client safe either.
 */
abstract class ClientError extends GraphQL
{
    #[Override]
    public function isClientSafe(): bool
    {
        $previous = $this->getPrevious();

        return $previous instanceof ClientAware ? $previous->isClientSafe() : $previous === null;
    }
}
