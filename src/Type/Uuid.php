<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

use function is_string;

class Uuid extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'A universally unique identifier.';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Uuid can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $valueNode->value;
    }

    public function parseValue(mixed $value): UuidInterface|null
    {
        if ($value instanceof UuidInterface) {
            return $value;
        }

        if (is_string($value)) {
            return RamseyUuid::fromString($value);
        }

        throw new Error('Uuid value is invalid: ' . $value);
    }

    public function serialize(mixed $value): string|null
    {
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        return $value;
    }
}
