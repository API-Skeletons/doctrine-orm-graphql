<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;
use Override;

use function floatval;

/**
 * Transform a number value into a php native float
 *
 * @returns float
 */
final class ToFloat extends Collection implements
    StrategyInterface
{
    #[Override]
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
    #[Override]
    public function hydrate(mixed $value, array|null $data): mixed
    {
        if ($value === null) {
            return $value;
        }

        return floatval($value);
    }
}
