<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use BackedEnum;
use Override;

/**
 * Return the same value
 */
final class FieldDefault implements Strategy
{
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
    {
        // Doctrine hydrates a field mapped with an enumType to a case of the enum
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): mixed
    {
        return $value;
    }
}
