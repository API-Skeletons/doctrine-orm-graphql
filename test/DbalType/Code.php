<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\DbalType;

use Stringable;

/**
 * A value object an identifier is held as, as a UUID is
 */
final class Code implements Stringable
{
    public function __construct(public readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
