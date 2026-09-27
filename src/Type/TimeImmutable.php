<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SerializeDateTime;
use DateTimeImmutable as PHPDateTimeImmutable;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function get_debug_type;
use function is_string;
use function preg_match;

/**
 * This class is used to create a TimeImmutable type
 */
final class TimeImmutable extends ScalarType
{
    use SerializeDateTime;

    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `TimeImmutable` scalar type represents time data.'
    . 'The format is e.g. 24 hour:minutes:seconds';

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
            throw new TypeSerializationException('Time is not a string: ' . get_debug_type($value));
        }

        if (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])(\.\d{1,6})?$/', $value)) {
            throw new TypeSerializationException('Time ' . $value . ' format does not match H:i:s.u e.g. 13:34:40.867530');
        }

        // If time does not have milliseconds, parse without
        $format = preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])$/', $value) ? 'H:i:s' : 'H:i:s.u';
        $time   = PHPDateTimeImmutable::createFromFormat($format, $value);

        // @codeCoverageIgnoreStart
        if ($time === false) {
            throw new TypeSerializationException('Time format does not match ' . $format . '.');
        }

        // @codeCoverageIgnoreEnd

        return $time;
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->serializeDateTime($value, 'H:i:s.u');
    }
}
