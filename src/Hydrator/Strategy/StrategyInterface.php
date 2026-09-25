<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use Laminas\Hydrator\Strategy\StrategyInterface as LaminasStrategyInterface;
use Override;

/**
 * A hydrator strategy which receives the name of the field being extracted.
 *
 * Strategies are shared instances in the HydratorContainer; one instance
 * serves every field which uses it.  The field name lets a strategy act
 * differently per field without holding per-field state.
 *
 * Strategies which implement only the Laminas StrategyInterface are still
 * supported; they are called without the field name.
 */
interface StrategyInterface extends LaminasStrategyInterface // phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming.SuperfluousSuffix
{
    /**
     * Converts the given value so that it can be extracted by the hydrator.
     *
     * @param mixed       $value     The original value.
     * @param object|null $object    The original object for context.
     * @param string|null $fieldName The Doctrine field name being extracted.  This is
     *                               the entity field name, not a GraphQL alias.
     *
     * @return mixed Returns the value that should be extracted.
     */
    #[Override]
    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed;
}
