<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use GraphQL\Error\Error;

use function array_flip;
use function implode;
use function key;
use function strcmp;
use function uasort;
use function uniqid;

/**
 * This class is used to add filters to a Doctrine QueryBuilder based on the
 * field filters
 */
class QueryBuilder
{
    /** @var mixed[]  */
    private array $sortFields = [];

    /**
     * Add where clauses to a QueryBuilder based on the FilterType of the entity
     *
     * @param array<string, mixed|array<string, mixed>> $filterTypes
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

                switch ($filter) {
                    case Filters::EQ:
                    case Filters::NEQ:
                    case Filters::GT:
                    case Filters::GTE:
                    case Filters::LT:
                    case Filters::LTE:
                    case Filters::IN:
                    case Filters::NOTIN:
                    case Filters::ISNULL:
                        // These filters are handled by the default method
                        $this->default($filter, $queryBuilderField, $value, $queryBuilder);
                        break;
                    default:
                        $this->{$filter->value}($queryBuilderField, $value, $queryBuilder);
                        break;
                }
            }
        }

        $this->applySort($queryBuilder);
    }

    /**
     * For filters that do not have a special method, use this method
     */
    protected function default(Filters $filter, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): void
    {
        switch ($filter) {
            case Filters::EQ:
            case Filters::NEQ:
            case Filters::GT:
            case Filters::GTE:
            case Filters::LT:
            case Filters::LTE:
            case Filters::IN:
            case Filters::NOTIN:
                break;
            case Filters::ISNULL:
                $parameter = 'p' . uniqid();
                $queryBuilder
                    ->andWhere(
                        $queryBuilder->expr()->{$filter->value}($field),
                    )
                    ->setParameter($parameter, $value);

                return;

            default:
                return;
        }

        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->{$filter->value}($field, ':' . $parameter),
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

        // Sort fields by priority if set, otherwise by field name
        uasort($this->sortFields, static function ($a, $b) {
            if (isset($a['priority']) && isset($b['priority'])) {
                return $a['priority'] <=> $b['priority'];
            }

            return strcmp(key($a), key($b));
        });

        $sortStrings = [];

        foreach ($this->sortFields as $field => $sort) {
            // If the direction is not set, default to 'ASC'
            if (! isset($sort['direction'])) {
                throw new Error(
                    "Sort direction for field '"
                    . $field
                    . "' is not set but a sortPriority was. "
                    . "Please use the 'sort' filter to set the direction.",
                );
            }

            $sortStrings[] = $field . ' ' . $sort['direction'];
        }

        $sortString = implode(', ', $sortStrings);
        $queryBuilder->addOrderBy($sortString);
    }
}
