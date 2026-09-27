<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use JsonException;
use Override;

use function get_debug_type;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * This class is used to create a Json type
 *
 * The value is exchanged as a string containing a JSON document.  Any JSON
 * document is valid: an object, an array, a string, a number, a boolean or
 * null.
 */
final class Json extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `json` scalar type represents json data.';

    /** @throws TypeSerializationException */
    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): mixed
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function parseValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('JSON is not a string: ' . get_debug_type($value));
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TypeSerializationException('Could not parse JSON data: ' . $e->getMessage(), null, $e);
        }
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TypeSerializationException('Could not serialize JSON data: ' . $e->getMessage(), null, $e);
        }
    }
}
