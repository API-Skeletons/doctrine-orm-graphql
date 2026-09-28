<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function base64_decode;
use function base64_encode;
use function get_debug_type;
use function is_resource;
use function is_string;
use function stream_get_contents;

/**
 * This class is used to create a Blob type
 */
final class Blob extends ScalarType
{
    public string|null $description = 'A binary file base64 encoded.';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('Blob field as base64 is not a string: ' . get_debug_type($value));
        }

        $data = base64_decode($value, true);

        if ($data === false) {
            throw new TypeSerializationException('Blob field contains non-base64 encoded characters');
        }

        return $data;
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);

            // @codeCoverageIgnoreStart
            if ($value === false) {
                throw new TypeSerializationException('Blob stream could not be read');
            }

            // @codeCoverageIgnoreEnd
        }

        // Every string is encoded, including a falsy one such as "0"
        if (! is_string($value)) {
            throw new TypeSerializationException('Expected a string or stream for Blob.  Got ' . get_debug_type($value) . '.');
        }

        return base64_encode($value);
    }
}
