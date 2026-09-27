<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\SerializeDateTime;
use DateTime;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;

use function get_debug_type;
use function is_string;
use function preg_match;

/**
 * This class is used to create a Date type
 */
final class Date extends ScalarType
{
    use SerializeDateTime;

    public string|null $description = 'The `Date` scalar type represents datetime data.'
    . 'The format is e.g. 2004-02-12.';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): DateTime
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): DateTime
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('Date is not a string: ' . get_debug_type($value));
        }

        if (! preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $value)) {
            throw new TypeSerializationException('Date format does not match Y-m-d e.g. 2004-02-12.');
        }

        $date = DateTime::createFromFormat(DateTime::ATOM, $value . 'T00:00:00+00:00');

        // @codeCoverageIgnoreStart
        if ($date === false) {
            throw new TypeSerializationException('Date format does not match Y-m-d e.g. 2004-02-12.');
        }

        // @codeCoverageIgnoreEnd

        return $date;
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        return $this->serializeDateTime($value, 'Y-m-d');
    }
}
