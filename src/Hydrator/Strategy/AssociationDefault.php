<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Override;

/**
 * Take no action on an association.  This class exists to
 * differentiate associations inside generated config.
 */
final class AssociationDefault extends Collection
{
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
    {
        return $value;
    }

    /**
     * @param mixed[] |null $data
     *
     * @codeCoverageIgnore
     */
    #[Override]
    public function hydrate(mixed $value, array|null $data): mixed
    {
        return $value;
    }
}
