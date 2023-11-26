<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use DateTime;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;
use function preg_match;

class Date extends ScalarType
{
    public string|null $description = 'The `Date` scalar type represents datetime data.'
    . 'The format is e.g. 2004-02-12.';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $valueNode->value;
    }

    public function parseValue(mixed $value): DateTime
    {
        if (! is_string($value)) {
            throw new Error('Date is not a string: ' . $value);
        }

        if (! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $value)) {
            throw new Error('Date format does not match Y-m-d e.g. 2004-02-12.');
        }

        $data = DateTime::createFromFormat('Y-m-d', $value);

        return $data;
    }

    public function serialize(mixed $value): string|null
    {
        if (is_string($value)) {
            throw new Error('Expected DateTime object.  Got string.');
        }

        if (! $value instanceof DateTime) {
            throw new Error('Expected DateTime object.  Got ' . $value::class);
        }

        return $value->format('Y-m-d');
    }
}
