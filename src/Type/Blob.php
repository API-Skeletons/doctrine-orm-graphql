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
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        /** @psalm-suppress MixedReturnStatement */
        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            /** @psalm-suppress MixedOperand */
            throw new TypeSerializationException('Blob field as base64 is not a string: ' . $value);
        }

        $data = base64_decode($value, true);

        if ($data === false) {
            throw new TypeSerializationException('Blob field contains non-base64 encoded characters');
        }

        return $data;
    }

    #[Override]
    public function serialize(mixed $value): mixed
    {
        if (! $value) {
            return $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);

            // @codeCoverageIgnoreStart
            if ($value === false) {
                return null;
            }

            // @codeCoverageIgnoreEnd
        }

        /** @psalm-suppress MixedArgument */
        return base64_encode($value);
    }
}
