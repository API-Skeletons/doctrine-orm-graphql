<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use DateTimeImmutable as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;
use function preg_match;

/**
 * This class is used to create a TimeImmutable type
 */
class TimeImmutable extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `Time` scalar type represents time data.'
    . 'The format is e.g. 24 hour:minutes:seconds';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTime|false
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        if (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])(\.\d{1,6})?$/', $valueNode->value)) {
            throw new Error('Time ' . $valueNode->value . ' format does not match H:i:s.u e.g. 13:34:40.867530');
        }

        // If time does not have milliseconds, parse without
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])$/', $valueNode->value)) {
            return PHPDateTime::createFromFormat('H:i:s', $valueNode->value);
        }

        return PHPDateTime::createFromFormat('H:i:s.u', $valueNode->value);
    }

    public function parseValue(mixed $value): PHPDateTime|false
    {
        if (! is_string($value)) {
            throw new Error('Time is not a string: ' . $value);
        }

        if (! preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])(\.\d{1,6})?$/', $value)) {
            throw new Error('Time ' . $value . ' format does not match H:i:s.u e.g. 13:34:40.867530');
        }

        // If time does not have milliseconds, parse without
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])$/', $value)) {
            return PHPDateTime::createFromFormat('H:i:s', $value);
        }

        return PHPDateTime::createFromFormat('H:i:s.u', $value);
    }

    public function serialize(mixed $value): string|null
    {
        if ($value instanceof PHPDateTime) {
            $value = $value->format('H:i:s.u');
        }

        return $value;
    }
}
