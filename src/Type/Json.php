<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Type\Definition\ScalarType;

use function is_string;
use function json_decode;
use function json_encode;

/**
 * This class is used to create a Json type
 */
class Json extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `json` scalar type represents json data.';

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
        if (! is_string($value)) {
            throw new Error('JSON is not a string: ' . $value);
        }

        $data = json_decode($value, true);

        if (! $data) {
            throw new Error('Could not parse JSON data');
        }

        return $data;
    }

    public function serialize(mixed $value): string|null
    {
        return json_encode($value);
    }
}
