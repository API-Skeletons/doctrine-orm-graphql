<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function is_string;
use function json_decode;
use function json_encode;

/**
 * This class is used to create a Json type
 */
final class Json extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `json` scalar type represents json data.';

    /** @return array<mixed> */
    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): array
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return mixed[]
     *
     * @throws TypeSerializationException
     */
    #[Override]
    public function parseValue(mixed $value): array
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('JSON is not a string: ' . $value);
        }

        $data = json_decode($value, true);

        if (! $data) {
            throw new TypeSerializationException('Could not parse JSON data');
        }

        return $data;
    }

    #[Override]
    public function serialize(mixed $value): string
    {
        $return = json_encode($value);

        if ($return === false) {
            throw new TypeSerializationException('Could not serialize JSON data');
        }

        return $return;
    }
}
