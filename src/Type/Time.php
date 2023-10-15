<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;

class Time extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `Time` scalar type represents time data.'
    . 'The format is e.g. 24 hour:minutes:seconds';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $valueNode->value;
    }

    public function parseValue(mixed $value): PHPDateTime
    {
        if (! is_string($value)) {
            throw new Error('Time is not a string: ' . $value);
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
