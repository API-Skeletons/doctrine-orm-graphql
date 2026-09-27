<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateTimeInterface;

use function get_debug_type;

/**
 * Serialize a date or time scalar.  Either DateTimeInterface class is
 * accepted because both format identically; anything else is an error.
 */
trait SerializeDateTime
{
    /** @throws TypeSerializationException */
    private function serializeDateTime(mixed $value, string $format): string
    {
        if (! $value instanceof DateTimeInterface) {
            throw new TypeSerializationException(
                'Expected a DateTimeInterface for ' . $this->name . '.  Got ' . get_debug_type($value) . '.',
            );
        }

        return $value->format($format);
    }
}
