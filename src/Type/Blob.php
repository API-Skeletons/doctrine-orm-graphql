<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

use function base64_decode;
use function base64_encode;
use function is_resource;
use function is_string;
use function stream_get_contents;

/**
 * This class is used to create a Blob type
 */
class Blob extends ScalarType
{
    public string|null $description = 'A binary file base64 encoded.';

    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): mixed
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        $data = base64_decode($valueNode->value, true);

        if ($data === false) {
            throw new Error('Blob field contains non-base64 encoded characters');
        }

        return $data;
    }

    public function parseValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            throw new Error('Blob field as base64 is not a string: ' . $value);
        }

        $data = base64_decode($value, true);

        if ($data === false) {
            throw new Error('Blob field contains non-base64 encoded characters');
        }

        return $data;
    }

    public function serialize(mixed $value): mixed
    {
        if (! $value) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return base64_encode($value);
    }
}
