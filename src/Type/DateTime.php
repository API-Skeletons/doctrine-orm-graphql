<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;

/**
 * This class is used to create a DateTime type
 */
class DateTime extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetime` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00.';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTime|null
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        if (! $valueNode->value) {
            return null;
        }

        $data = PHPDateTime::createFromFormat(PHPDateTime::ATOM, $valueNode->value);

        if ($data === false) {
            throw new Error('datetime format does not match ISO 8601.');
        }

        return $data;
    }

    public function parseValue(mixed $value): PHPDateTime
    {
        if (! is_string($value)) {
            throw new Error('datetime is not a string: ' . $value);
        }

        $data = PHPDateTime::createFromFormat(PHPDateTime::ATOM, $value);

        if ($data === false) {
            throw new Error('datetime format does not match ISO 8601.');
        }

        return $data;
    }

    public function serialize(mixed $value): string|null
    {
        if ($value instanceof PHPDateTime) {
            $value = $value->format(PHPDateTime::ATOM);
        }

        return $value;
    }
}
