<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Exception;

use function array_udiff;
use function array_uintersect;

/**
 * A common function to compute excluded filters from included
 * and excluded filters.
 */
trait ExcludeFilters
{
    /** @var Filters[] */
    private array $includeFilters = [];

    /** @var Filters[] */
    private array $excludeFilters = [];

    /** @return Filters[] */
    public function getExcludeFilters(): array
    {
        if ($this->includeFilters && $this->excludeFilters) {
            throw new Exception('includeFilters and excludeFilters are mutually exclusive.');
        }

        if ($this->includeFilters) {
            $this->excludeFilters = array_udiff(
                Filters::cases(),
                $this->includeFilters,
                static function ($a1, $a2) {
                    return $a1->value <=> $a2->value;
                },
            );
        } elseif ($this->excludeFilters) {
            $this->excludeFilters = array_uintersect(
                Filters::cases(),
                $this->excludeFilters,
                static function ($a1, $a2) {
                    return $a1->value <=> $a2->value;
                },
            );
        }

        return $this->excludeFilters;
    }
}
