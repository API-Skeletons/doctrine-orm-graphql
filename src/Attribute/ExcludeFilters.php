<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

use function array_udiff;
use function array_uintersect;
use function count;

/**
 * A common function to compute excluded filters from included
 * and excluded filters.
 */
trait ExcludeFilters
{
    /** @var Filters[] */
    private readonly array $includeFilters;

    /** @var Filters[] */
    private readonly array $excludeFilters;

    /**
     * @return Filters[]
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getExcludeFilters(): array
    {
        $filters = [];

        if (count($this->includeFilters) && count($this->excludeFilters)) {
            throw new ConfigurationException(
                'includeFilters and excludeFilters are mutually exclusive. ' .
                'Use either includeFilters OR excludeFilters, not both.',
            );
        }

        if (count($this->includeFilters)) {
            $filters = array_udiff(
                Filters::cases(),
                $this->includeFilters,
                static function (Filters $a1, Filters $a2) {
                    return $a1->value <=> $a2->value;
                },
            );
        } elseif (count($this->excludeFilters)) {
            $filters = array_uintersect(
                Filters::cases(),
                $this->excludeFilters,
                static function (Filters $a1, Filters $a2) {
                    return $a1->value <=> $a2->value;
                },
            );
        }

        return $filters;
    }
}
