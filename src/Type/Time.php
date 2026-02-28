<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateTime as PHPDateTime;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function is_string;
use function preg_match;

/**
 * This class is used to create a Time type
 */
final class Time extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `Time` scalar type represents time data.'
    . 'The format is e.g. 24 hour:minutes:seconds.microseconds';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $valueNode->value;
    }

    /**
     * Parse H:i:s.u and H:i:s
     */
    #[Override]
    public function parseValue(mixed $value): PHPDateTime
    {
        if (! is_string($value)) {
            /** @psalm-suppress MixedOperand */
            throw new TypeSerializationException('Time is not a string: ' . $value);
        }

        if (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])(\.\d{1,6})?$/', $value)) {
            throw new TypeSerializationException('Time ' . $value . ' format does not match H:i:s.u e.g. 13:34:40.867530');
        }

        // If time does not have milliseconds, parse without
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])$/', $value)) {
            $time = PHPDateTime::createFromFormat('H:i:s', $value);

            if ($time === false) {
                throw new TypeSerializationException('Time format does not match H:i:s.');
            }

            return $time;
        }

        $time = PHPDateTime::createFromFormat('H:i:s.u', $value);

        if ($time === false) {
            throw new TypeSerializationException('Time format does not match H:i:s.u.');
        }

        return $time;
    }

    #[Override]
    public function serialize(mixed $value): string|null
    {
        if ($value instanceof PHPDateTime) {
            return $value->format('H:i:s.u');
        }

        return is_string($value) ? $value : null;
    }
}
