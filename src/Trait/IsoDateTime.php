<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

use function preg_match;
use function substr;

/**
 * Parse and serialize an ISO 8601 date and time with an offset, such as
 * 2004-02-12T15:19:21+00:00, with or without a fraction of a second, such as
 * 2004-02-12T15:19:21.123Z from JavaScript's Date.toISOString().
 */
trait IsoDateTime
{
    use RejectInvalidDateTime;
    use SerializeDateTime;

    /**
     * A PHP date and time holds microseconds, so digits of a fraction beyond
     * the sixth are truncated
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws TypeSerializationException
     *
     * @template T of DateTime|DateTimeImmutable
     */
    private function parseIsoDateTime(string $value, string $class): DateTime|DateTimeImmutable
    {
        if (preg_match('/^(.{19})\.(\d+)(.*)$/', $value, $matches) === 1) {
            $data = $class::createFromFormat('Y-m-d\TH:i:s.uP', $matches[1] . '.' . substr($matches[2], 0, 6) . $matches[3]);
        } else {
            $data = $class::createFromFormat(DateTimeInterface::ATOM, $value);
        }

        if ($data === false) {
            throw new TypeSerializationException($this->name . ' format does not match ISO 8601.');
        }

        $this->rejectInvalidDateTime($value);

        return $data;
    }

    /**
     * A fraction of a second is serialized only when there is one, so a
     * value of whole seconds is serialized as DateTimeInterface::ATOM
     *
     * @throws TypeSerializationException
     */
    private function serializeIsoDateTime(mixed $value): string
    {
        $hasFraction = $value instanceof DateTimeInterface && $value->format('u') !== '000000';

        return $this->serializeDateTime($value, $hasFraction ? 'Y-m-d\TH:i:s.uP' : DateTimeInterface::ATOM);
    }
}
