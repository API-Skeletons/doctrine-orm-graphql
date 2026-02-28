<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

use function array_flip;
use function in_array;
use function key;
use function strcmp;
use function strtoupper;
use function uasort;
use function uniqid;

/**
 * This class is used to add filters to a Doctrine QueryBuilder based on the
 * field filters
 */
final class QueryBuilder
{
    /** @var mixed[]  */
    private array $sortFields = [];

    /**
     * Add where clauses to a QueryBuilder based on the FilterType of the entity
     *
     * @param array<string, mixed|array<string, mixed>> $filterTypes
     *
     * @psalm-suppress MixedAssignment, MixedArgument
     */
    public function apply(
        array $filterTypes,
        DoctrineQueryBuilder $queryBuilder,
        Entity $entity,
    ): void {
        foreach ($filterTypes as $field => $filters) {
            // Resolve aliases
            $field             = array_flip($entity->getExtractionMap())[$field] ?? $field;
            $queryBuilderField = 'entity.' . $field;

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                if (
                    in_array($filter, [
                        Filters::EQ,
                        Filters::NEQ,
                        Filters::GT,
                        Filters::GTE,
                        Filters::LT,
                        Filters::LTE,
                        Filters::IN,
                        Filters::NOTIN,
                    ])
                ) {
                    $this->default($filter->value, $queryBuilderField, $value, $queryBuilder);
                    continue;
                }

                if ($filter === Filters::ISNULL) {
                    /** @psalm-suppress MixedArgument */
                    $this->isnull($queryBuilderField, $value, $queryBuilder);
                    continue;
                }

                $this->{$filter->value}($queryBuilderField, $value, $queryBuilder);
            }
        }

        $this->applySort($queryBuilder);
    }

    /**
     * For filters that do not have a special method, use this method
     */
    protected function default(string $filterValue, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->$filterValue($field, ':' . $parameter),
            )
            ->setParameter($parameter, $value);
    }

    /** @param array<string, mixed> $value */
    protected function between(string $field, array $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $from = 'p' . uniqid();
        $to   = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->between(
                    $field,
                    ':' . $from,
                    ':' . $to,
                ),
            )
            ->setParameter($from, $value['from'])
            ->setParameter($to, $value['to']);
    }

    protected function contains(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->like($field, ':' . $parameter),
            )
            ->setParameter($parameter, '%' . $value . '%');
    }

    protected function startsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->like($field, ':' . $parameter),
            )
            ->setParameter($parameter, $value . '%');
    }

    protected function endsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->like($field, ':' . $parameter),
            )
            ->setParameter($parameter, '%' . $value);
    }

    protected function isnull(string $field, bool $value, DoctrineQueryBuilder $queryBuilder): void
    {
        if ($value === true) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->isNull($field),
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->isNotNull($field),
            );
        }
    }

    protected function sort(string $field, string $direction, DoctrineQueryBuilder $queryBuilder): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort direction for a field
        // It will be used to apply sorting later in the applySort method
        /** @psalm-suppress MixedArrayAssignment */
        $this->sortFields[$field]['direction'] = strtoupper($direction);
    }

    protected function sortPriority(string $field, int $priority, DoctrineQueryBuilder $queryBuilder): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort priority for a field
        // It will be used to apply sorting later in the applySort method
        /** @psalm-suppress MixedArrayAssignment */
        $this->sortFields[$field]['priority'] = $priority;
    }

    protected function applySort(DoctrineQueryBuilder $queryBuilder): void
    {
        // If no sort fields were added, do nothing
        if (! $this->sortFields) {
            return;
        }

        // Sort fields by priority if set, otherwise by field name
        /** @psalm-suppress MixedArrayAccess, MixedArgument, MixedArgumentTypeCoercion */
        uasort($this->sortFields, static function ($a, $b) {
            if (isset($a['priority']) && isset($b['priority'])) {
                return $a['priority'] <=> $b['priority'];
            }

            return strcmp(key($a) ?? '', key($b) ?? '');
        });

        $sortStrings = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($this->sortFields as $field => $sort) {
            // If the direction is not set, default to 'ASC'
            if (! isset($sort['direction'])) {
                throw new FilterException(
                    "Sort direction for field '"
                    . $field
                    . "' is not set but a sortPriority was. "
                    . "Please use the 'sort' filter to set the direction.",
                );
            }

            /** @psalm-suppress MixedArrayAccess, MixedArgument, MixedArgumentTypeCoercion */
            $queryBuilder->addOrderBy($field, $sort['direction']);
        }
    }
}
