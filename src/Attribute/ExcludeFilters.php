<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

use function array_udiff;
use function array_uintersect;
use function array_values;
use function count;
use function get_debug_type;
use function is_string;

/**
 * A common function to compute excluded filters from included
 * and excluded filters.
 */
trait ExcludeFilters
{
    /** @var array<Filters|string> */
    private readonly array $includeFilters;

    /** @var array<Filters|string> */
    private readonly array $excludeFilters;

    /**
     * @return Filters[]
     *
     * @throws ConfigurationException
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getExcludeFilters(): array
    {
        $includeFilters = $this->toFilters($this->includeFilters, 'includeFilters');
        $excludeFilters = $this->toFilters($this->excludeFilters, 'excludeFilters');

        if (count($includeFilters) && count($excludeFilters)) {
            throw new ConfigurationException(
                'includeFilters and excludeFilters are mutually exclusive. ' .
                'Use either includeFilters OR excludeFilters, not both.',
            );
        }

        if (count($includeFilters)) {
            return array_values(array_udiff(
                Filters::cases(),
                $includeFilters,
                static fn (Filters $a1, Filters $a2): int => $a1->value <=> $a2->value,
            ));
        }

        return array_values(array_uintersect(
            Filters::cases(),
            $excludeFilters,
            static fn (Filters $a1, Filters $a2): int => $a1->value <=> $a2->value,
        ));
    }

    /**
     * A filter may be given as a Filters case or its value, as it may to Config
     *
     * @param array<array-key, mixed> $filters
     *
     * @return list<Filters>
     *
     * @throws ConfigurationException
     */
    private function toFilters(array $filters, string $argument): array
    {
        $cases = [];

        /** @psalm-suppress MixedAssignment Each filter is checked */
        foreach ($filters as $filter) {
            $cases[] = match (true) {
                $filter instanceof Filters => $filter,
                is_string($filter) && Filters::tryFrom($filter) !== null => Filters::from($filter),
                default => throw new ConfigurationException(
                    'Invalid ' . $argument . ' filter: '
                    . (is_string($filter) ? '"' . $filter . '"' : get_debug_type($filter)) . ' is not a filter.',
                    Filters::toStringArray(Filters::cases()),
                ),
            };
        }

        return $cases;
    }
}
