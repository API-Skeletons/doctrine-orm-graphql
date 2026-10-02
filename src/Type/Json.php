<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;
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
 * With JsonFormat::String, the default, the value is exchanged as a string
 * containing a JSON document.  With JsonFormat::Object it is exchanged as the
 * value itself.  Any JSON value is valid: an object, an array, a string, a
 * number, a boolean or null.
 */
final class Json extends ScalarType
{
    public function __construct(private readonly JsonFormat $format = JsonFormat::String)
    {
        parent::__construct([
            'description' => $format === JsonFormat::String
                ? 'The `Json` scalar type represents JSON data as a string containing a JSON document.'
                : 'The `Json` scalar type represents JSON data as the value itself.',
        ]);
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): mixed
    {
        if ($this->format === JsonFormat::Object) {
            return AST::valueFromASTUntyped($valueNode, $variables);
        }

        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function parseValue(mixed $value): mixed
    {
        if ($this->format === JsonFormat::Object) {
            return $value;
        }

        if (! is_string($value)) {
            throw new TypeSerializationException($this->name . ' is not a string: ' . get_debug_type($value));
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // The client is told so, rather than shown PHP's message
            throw new TypeSerializationException($this->name . ' is not a valid JSON document.');
        }
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): mixed
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TypeSerializationException('Could not serialize JSON data: ' . $e->getMessage(), null, $e);
        }

        return $this->format === JsonFormat::String ? $json : $value;
    }
}
