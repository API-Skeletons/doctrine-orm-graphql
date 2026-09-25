<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\StrategyInterface;

/**
 * Prefix the extracted value with the name of the field being extracted
 */
class PrefixFieldName implements
    StrategyInterface
{
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): string
    {
        return $fieldName . ':' . (string) $value;
    }

    /** @param mixed[]|null $data */
    public function hydrate(mixed $value, array|null $data = null): mixed
    {
        return $value;
    }
}
