<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use BackedEnum;
use Override;

/**
 * Transform a value into a php native boolean
 *
 * @returns boolean
 */
final class ToBoolean implements Strategy
{
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): bool|null
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

        return (bool) $value;
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): bool|null
    {
        if ($value === null) {
            return $value;
        }

        return (bool) $value;
    }
}
