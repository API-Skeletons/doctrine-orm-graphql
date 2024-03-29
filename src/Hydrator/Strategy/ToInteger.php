<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

use function intval;

/**
 * Transform a number value into a php native integer
 *
 * @returns integer
 */
class ToInteger extends Collection implements
    StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): mixed
    {
        if ($value === null) {
            // @codeCoverageIgnoreStart
            return $value;
            // @codeCoverageIgnoreEnd
        }

        return intval($value);
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    public function hydrate(mixed $value, array|null $data): mixed
    {
        if ($value === null) {
            return $value;
        }

        return intval($value);
    }
}
