<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use BackedEnum;
use Override;

use function floatval;

/**
 * Transform a number value into a php native float
 *
 * @returns float
 */
final class ToFloat implements Strategy
{
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
    {
        if ($value === null) {
            // @codeCoverageIgnoreStart
            return $value;
            // @codeCoverageIgnoreEnd
        }

        // Doctrine hydrates a field mapped with an enumType to a case of the enum
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return floatval($value);
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): mixed
    {
        if ($value === null) {
            return $value;
        }

        return floatval($value);
    }
}
