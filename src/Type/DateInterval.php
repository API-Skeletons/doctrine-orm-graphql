<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use DateInterval as PHPDateInterval;
use GraphQL\Language\AST\Node as ASTNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Override;
use Throwable;

use function get_debug_type;
use function is_string;
use function preg_match;

/**
 * This class is used to create a DateInterval type
 */
final class DateInterval extends ScalarType
{
    // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
    public string $name = 'DateInterval';

    public string|null $description = 'The `DateInterval` scalar type represents a duration.'
    . 'The format is ISO 8601, optionally signed, e.g. P1Y2M3DT4H5M6S or -P1D.';

    #[Override]
    public function parseLiteral(ASTNode $valueNode, array|null $variables = null): PHPDateInterval
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new TypeSerializationException('Query error: Can only parse strings got: ' . $valueNode->kind, $valueNode);
        }

        return $this->parseValue($valueNode->value);
    }

    #[Override]
    public function parseValue(mixed $value): PHPDateInterval
    {
        if (! is_string($value)) {
            throw new TypeSerializationException('DateInterval is not a string: ' . get_debug_type($value));
        }

        // The DateInterval constructor accepts neither a sign nor an empty duration
        if (! preg_match('/^([+-]?)(P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?!$)(\d+H)?(\d+M)?(\d+S)?)?)$/', $value, $matches)) {
            throw new TypeSerializationException('DateInterval ' . $value . ' does not match ISO 8601 e.g. P1Y2M3DT4H5M6S.');
        }

        try {
            $interval = new PHPDateInterval($matches[2]);
        } catch (Throwable $e) {
            // A number too large for PHP
            throw new TypeSerializationException('DateInterval ' . $value . ' does not match ISO 8601.', null, $e);
        }

        $interval->invert = $matches[1] === '-' ? 1 : 0;

        return $interval;
    }

    /** @throws TypeSerializationException */
    #[Override]
    public function serialize(mixed $value): string
    {
        if (! $value instanceof PHPDateInterval) {
            throw new TypeSerializationException(
                'Expected a DateInterval for ' . $this->name . '.  Got ' . get_debug_type($value) . '.',
            );
        }

        $date = ($value->y ? $value->y . 'Y' : '')
            . ($value->m ? $value->m . 'M' : '')
            . ($value->d ? $value->d . 'D' : '');
        $time = ($value->h ? $value->h . 'H' : '')
            . ($value->i ? $value->i . 'M' : '')
            . ($value->s ? $value->s . 'S' : '');

        if ($date === '' && $time === '') {
            return 'PT0S';
        }

        return ($value->invert ? '-' : '') . 'P' . $date . ($time === '' ? '' : 'T' . $time);
    }
}
