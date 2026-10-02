<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\IsoDateTime;
use DateTimeImmutable as PHPDateTimeTZImmutable;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function get_debug_type;
use function is_string;

/**
 * This class is used to create a DateTimeImmutable type
 */
final class DateTimeTZImmutable extends ScalarType
{
    use IsoDateTime;

    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetimetz_immutable` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00 or 2004-02-12T15:19:21.123Z';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTimeTZImmutable
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): PHPDateTimeTZImmutable
    {
        if (! is_string($value)) {
            throw new TypeSerializationException($this->name . ' is not a string: ' . get_debug_type($value));
        }

        return $this->parseIsoDateTime($value, PHPDateTimeTZImmutable::class);
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->serializeIsoDateTime($value);
    }
}
