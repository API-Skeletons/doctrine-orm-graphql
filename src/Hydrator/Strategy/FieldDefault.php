<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;
use Override;

/**
 * Return the same value
 */
final class FieldDefault extends Collection implements
    StrategyInterface
{
    #[Override]
    public function extract(mixed $value, object|null $object = null): mixed
    {
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
