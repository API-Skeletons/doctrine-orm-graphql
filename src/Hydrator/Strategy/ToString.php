<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Override;

use function strval;

/**
 * Transform a scalar or Stringable value into a php native string
 *
 * @returns string
 */
final class ToString implements Strategy
{
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): string|null
    {
        if ($value === null) {
            return $value;
        }

        /** @psalm-suppress MixedArgument */
        return strval($value);
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): string|null
    {
        if ($value === null) {
            return $value;
        }

        /** @psalm-suppress MixedArgument */
        return strval($value);
    }
}
