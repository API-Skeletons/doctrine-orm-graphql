<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Filters;
use Exception;

use function array_diff;
use function array_intersect;

trait ExcludeCriteria
{
    /** @return string[] */
    public function getExcludeCriteria(): array
    {
        if ($this->includeCriteria && $this->excludeCriteria) {
            throw new Exception('includeCriteria and excludeCriteria are mutually exclusive.');
        }

        if ($this->includeCriteria) {
            $this->excludeCriteria = array_diff(Filters::toArray(), $this->includeCriteria);
        } elseif ($this->excludeCriteria) {
            $this->excludeCriteria = array_intersect(Filters::toArray(), $this->excludeCriteria);
        }

        return $this->excludeCriteria;
    }
}
