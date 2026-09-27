<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

use function array_flip;
use function in_array;
use function strcmp;
use function uksort;
use function uniqid;

/**
 * This class is used to add filters to a Doctrine QueryBuilder based on the
 * field filters
 */
final class QueryBuilder
{
    /**
     * The sort direction and priority of each sorted field, keyed by field
     *
     * @var array<string, array{direction?: string, priority?: int}>
     */
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
        // It will be used to apply sorting later in the applySort method.
        // The SortDirection enum guarantees the direction is ASC or DESC.
        $this->sortFields[$field]['direction'] = $direction;
    }

    protected function sortPriority(string $field, int $priority, DoctrineQueryBuilder $queryBuilder): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort priority for a field
        // It will be used to apply sorting later in the applySort method
        $this->sortFields[$field]['priority'] = $priority;
    }

    protected function applySort(DoctrineQueryBuilder $queryBuilder): void
    {
        // If no sort fields were added, do nothing
        if (! $this->sortFields) {
            return;
        }

        // Fields with a priority come first, lowest priority first.  Fields
        // without a priority follow.  Ties are ordered by field name.
        $sortFields = $this->sortFields;
        uksort($sortFields, static function (string $a, string $b) use ($sortFields): int {
            $priorityA = $sortFields[$a]['priority'] ?? null;
            $priorityB = $sortFields[$b]['priority'] ?? null;

            if ($priorityA !== $priorityB) {
                if ($priorityA === null) {
                    return 1;
                }

                if ($priorityB === null) {
                    return -1;
                }

                return $priorityA <=> $priorityB;
            }

            return strcmp($a, $b);
        });

        foreach ($sortFields as $field => $sort) {
            // A sortPriority without a sort direction is an error
            if (! isset($sort['direction'])) {
                throw new FilterException(
                    "Sort direction for field '"
                    . $field
                    . "' is not set but a sortPriority was. "
                    . "Please use the 'sort' filter to set the direction.",
                );
            }

            $queryBuilder->addOrderBy($field, $sort['direction']);
        }
    }
}
