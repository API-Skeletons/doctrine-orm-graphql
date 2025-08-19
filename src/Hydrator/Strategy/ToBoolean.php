<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;
use Override;

/**
 * Transform a value into a php native boolean
 *
 * @returns float
 */
class ToBoolean extends Collection implements
    StrategyInterface
{
    #[Override]
    public function extract(mixed $value, object|null $object = null): bool|null
    {
        if ($value === null) {
            // @codeCoverageIgnoreStart
            return $value;
            // @codeCoverageIgnoreEnd
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
