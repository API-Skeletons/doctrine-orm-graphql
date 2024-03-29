<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface;

/**
 * Take no action on an association.  This class exists to
 * differentiate associations inside generated config.
 */
class AssociationDefault extends CollectionStrategy implements
    StrategyInterface
{
    public function extract(mixed $value, object|null $object = null): mixed
    {
        return $value;
    }

    /**
     * @param mixed[] |null $data
     *
     * @codeCoverageIgnore
     */
    public function hydrate(mixed $value, array|null $data): mixed
    {
        return $value;
    }
}
