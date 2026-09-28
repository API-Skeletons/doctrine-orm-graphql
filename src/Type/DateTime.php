<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\RejectInvalidDateTime;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SerializeDateTime;
use DateTime as PHPDateTime;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function get_debug_type;
use function is_string;

/**
 * This class is used to create a DateTime type
 */
final class DateTime extends ScalarType
{
    use RejectInvalidDateTime;
    use SerializeDateTime;

    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetime` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00.';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTime
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): PHPDateTime
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('datetime is not a string: ' . get_debug_type($value));
        }

        $data = PHPDateTime::createFromFormat(PHPDateTime::ATOM, $value);

        if ($data === false) {
            throw new TypeSerializationException('datetime format does not match ISO 8601.');
        }

        $this->rejectInvalidDateTime($value);

        return $data;
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->serializeDateTime($value, PHPDateTime::ATOM);
    }
}
