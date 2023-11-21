<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Type\Definition\ScalarType;
use Ramsey\Uuid\Uuid;

use function is_string;
use function json_decode;
use function json_encode;

class Uuid extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'A universally unique identifier.';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        throw new Error('JSON fields are not searchable', $valueNode);
    }

    /**
     * @return mixed[]|null
     *
     * @throws Error
     */
    public function parseValue(mixed $value): array|null
    {
        if (is_string($value)) {
            return Uuid
        }

        if (! is_string($value)) {
            throw new Error('Json is not a string: ' . $value);
        }

        return json_decode($value, true);
    }

    public function serialize(mixed $value): string|null
    {
        return json_encode($value);
    }
}
