<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

use function array_flip;
use function method_exists;
use function uniqid;

/**
 * This class is used to add filters to a Doctrine QueryBuilder based on the
 * field filters
 */
class QueryBuilder
{
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
            $field             = array_flip($entity->getAliasMap())[$field] ?? $field;
            $queryBuilderField = 'entity.' . $field;

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                if (method_exists($this, $filter->value) === false) {
                    $this->default($filter, $queryBuilderField, $value, $queryBuilder);
                } else {
                    $this->{$filter->value}($queryBuilderField, $value, $queryBuilder);
                }
            }
        }
    }

    /**
     * For filters that do not have a special method, use this method
     */
    protected function default(Filters $filter, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): void
    {
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

    public function startsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->like($field, ':' . $parameter),
            )
            ->setParameter($parameter, $value . '%');
    }

    public function endsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->like($field, ':' . $parameter),
            )
            ->setParameter($parameter, '%' . $value);
    }

    public function isnull(string $field, bool $value, DoctrineQueryBuilder $queryBuilder): void
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
        $queryBuilder->addOrderBy($field, $direction);
    }
}
