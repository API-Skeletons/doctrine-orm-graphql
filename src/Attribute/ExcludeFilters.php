<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Exception;

use function array_diff;
use function array_intersect;

trait ExcludeFilters
{
    /** @return Filters[] */
    public function getExcludeFilters(): array
    {
        if ($this->includeFilters && $this->excludeFilters) {
            throw new Exception('includeFilters and excludeFilters are mutually exclusive.');
        }

        if ($this->includeFilters) {
            $this->excludeFilters = array_diff(Filters::toArray(), $this->includeFilters);
        } elseif ($this->excludeFilters) {
            $this->excludeFilters = array_intersect(Filters::toArray(), $this->excludeFilters);
        }

        return $this->excludeFilters;
    }
}
