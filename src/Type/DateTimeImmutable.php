<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use DateTimeImmutable as PHPDateTimeImmutable;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;

class DateTimeImmutable extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetime_immutable` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $valueNode->value;
    }

    public function parseValue(mixed $value): PHPDateTimeImmutable|false
    {
        if (! is_string($value)) {
            throw new Error('datetime_immutable is not a string: ' . $value);
        }

        $data = PHPDateTimeImmutable::createFromFormat(PHPDateTimeImmutable::ATOM, $value);

        if ($data === false) {
            throw new Error('datetime_immutable format does not match ISO 8601.');
        }

        return $data;
    }

    public function serialize(mixed $value): string|null
    {
        if ($value instanceof PHPDateTimeImmutable) {
            $value = $value->format(PHPDateTimeImmutable::ATOM);
        }

        return $value;
    }
}
