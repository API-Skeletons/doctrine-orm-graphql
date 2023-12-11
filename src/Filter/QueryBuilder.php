<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

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
        string $alias = 'entity',
    ): void {
        foreach ($filterTypes as $field => $filters) {
            $entityField = $alias . '.' . $field;

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                switch ($filter) {
                    case Filters::BETWEEN:
                        $from = 'p' . uniqid();
                        $to   = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->between(
                                $entityField,
                                ':' . $from,
                                ':' . $to,
                            ),
                        )
                            ->setParameter($from, $value['from'])
                            ->setParameter($to, $value['to']);
                        break;

                    case Filters::CONTAINS:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, '%' . $value . '%');
                        break;

                    case Filters::STARTSWITH:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value . '%');
                        break;

                    case Filters::ENDSWITH:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, '%' . $value);
                        break;

                    case Filters::ISNULL:
                        if ($value === true) {
                            $queryBuilder->andWhere(
                                $queryBuilder->expr()->isNull($entityField),
                            );
                        }

                        if ($value === false) {
                            $queryBuilder->andWhere(
                                $queryBuilder->expr()->isNotNull($entityField),
                            );
                        }

                        break;

                    case Filters::SORT:
                        $queryBuilder->addOrderBy($entityField, $value);
                        break;

                    default:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->{$filter->value}($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;
                }
            }
        }
    }
}
