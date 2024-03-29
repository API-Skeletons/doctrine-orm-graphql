<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * Return the same value
 */
class FieldDefault extends CollectionStrategy implements
    StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): mixed
    {
        return $value;
    }

    /**
     * @param mixed[]|null $data
     *
     * @codeCoverageIgnore
     */
    public function hydrate(mixed $value, array|null $data): mixed
    {
        return $value;
    }
}
