<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\RejectInvalidDateTime;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SerializeDateTime;
use DateTimeImmutable as PHPDateTimeImmutable;
use DateTimeZone;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function date_default_timezone_get;
use function get_debug_type;
use function is_string;

/**
 * This class is used to create a DateTimeImmutable type
 */
final class DateTimeImmutable extends ScalarType
{
    use RejectInvalidDateTime;
    use SerializeDateTime;

    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetime_immutable` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTimeImmutable
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): PHPDateTimeImmutable
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('datetime_immutable is not a string: ' . get_debug_type($value));
        }

        $data = PHPDateTimeImmutable::createFromFormat(PHPDateTimeImmutable::ATOM, $value);

        if ($data === false) {
            throw new TypeSerializationException('datetime_immutable format does not match ISO 8601.');
        }

        $this->rejectInvalidDateTime($value);

        // A datetime column stores the date and time without an offset, and
        // Doctrine reads it in the default timezone, so the value is given
        // in the default timezone.  It is the same instant.
        return $data->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->serializeDateTime($value, PHPDateTimeImmutable::ATOM);
    }
}
