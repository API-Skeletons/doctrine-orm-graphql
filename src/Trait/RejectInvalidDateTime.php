<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateTime;

/**
 * createFromFormat() accepts a date or time which does not exist, such as
 * 2004-02-31, and rolls it over to 2004-03-02 with only a warning.  Call this
 * after createFromFormat() to reject it.
 */
trait RejectInvalidDateTime
{
    /** @throws TypeSerializationException */
    private function rejectInvalidDateTime(string $value): void
    {
        // DateTime and DateTimeImmutable share the last errors
        $errors = DateTime::getLastErrors();

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new TypeSerializationException($this->name . ' ' . $value . ' is not a valid date or time.');
        }
    }
}
