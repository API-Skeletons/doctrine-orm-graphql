<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

use function floatval;

/**
 * Transform a number value into a php native float
 *
 * @returns float
 */
class ToFloat extends AbstractCollectionStrategy implements
    StrategyInterface
{
    /** @param mixed|null $object */
    public function extract(mixed $value, object|null $object = null): mixed
    {
        if ($value === null) {
            // @codeCoverageIgnoreStart
            return $value;
            // @codeCoverageIgnoreEnd
        }

        return floatval($value);
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

        return floatval($value);
    }
}
