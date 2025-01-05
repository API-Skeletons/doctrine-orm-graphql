<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

use function explode;

class CsvString implements
    StrategyInterface
{
    /** @return String[] */
    public function extract(mixed $value, object|null $object = null): array
    {
        if (! $value) {
            return [];
        }

        return explode(',', (string) $value);
    }

    /** @param mixed[]|null $data */
    public function hydrate(mixed $value, array|null $data = null): mixed
    {
        return $value;
    }
}
