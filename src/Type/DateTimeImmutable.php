<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateTimeImmutable as PHPDateTimeImmutable;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function is_string;

/**
 * This class is used to create a DateTimeImmutable type
 */
final class DateTimeImmutable extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string|null $description = 'The `datetime_immutable` scalar type represents datetime data.'
    . 'The format is ISO-8601 e.g. 2004-02-12T15:19:21+00:00';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateTimeImmutable
    {
        // @codeCoverageIgnoreStart
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        // @codeCoverageIgnoreEnd

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): PHPDateTimeImmutable
    {
        if (! is_string($value)) {
            /** @psalm-suppress MixedOperand */
            throw new TypeSerializationException('datetime_immutable is not a string: ' . $value);
        }

        $data = PHPDateTimeImmutable::createFromFormat(PHPDateTimeImmutable::ATOM, $value);

        if ($data === false) {
            throw new TypeSerializationException('datetime_immutable format does not match ISO 8601.');
        }

        return $data;
    }

    #[Override]
    public function serialize(mixed $value): string|null
    {
        if ($value instanceof PHPDateTimeImmutable) {
            return $value->format(PHPDateTimeImmutable::ATOM);
        }

        return is_string($value) ? $value : null;
    }
}
