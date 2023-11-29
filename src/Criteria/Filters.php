<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Criteria;

use Doctrine\ORM\QueryBuilder;

use function implode;
use function uniqid;

final class Filters
{
    public const EQ         = 'eq';
    public const NEQ        = 'neq';
    public const LT         = 'lt';
    public const LTE        = 'lte';
    public const GT         = 'gt';
    public const GTE        = 'gte';
    public const BETWEEN    = 'between';
    public const CONTAINS   = 'contains';
    public const STARTSWITH = 'startswith';
    public const ENDSWITH   = 'endswith';
    public const IN         = 'in';
    public const NOTIN      = 'notin';
    public const ISNULL     = 'isnull';
    public const SORT       = 'sort';

    /** @return string[] */
    public static function toArray(): array
    {
        return [
            self::EQ,
            self::NEQ,
            self::LT,
            self::LTE,
            self::GT,
            self::GTE,
            self::BETWEEN,
            self::CONTAINS,
            self::STARTSWITH,
            self::ENDSWITH,
            self::IN,
            self::NOTIN,
            self::ISNULL,
            self::SORT,
        ];
    }

    /** @return string[] */
    public static function getDescriptions(): array
    {
        return [
            self::EQ         => 'Equals. DateTime not supported.',
            self::NEQ        => 'Not equals',
            self::LT         => 'Less than',
            self::LTE        => 'Less than or equals',
            self::GT         => 'Greater than',
            self::GTE        => 'Greater than or equals',
            self::BETWEEN    => 'Is between from and to inclusive of from and to.  Good substitute for DateTime Equals.',
            self::CONTAINS   => 'Contains the value.  Strings only.',
            self::STARTSWITH => 'Starts with the value.  Strings only.',
            self::ENDSWITH   => 'Ends with the value.  Strings only.',
            self::IN         => 'In the list of values as an array',
            self::NOTIN      => 'Not in the list of values as an array',
            self::ISNULL     => 'Takes a boolean.  If TRUE return results where the field is null. '
                . 'If FALSE returns results where the field is not null. '
                . 'Acts as "isEmpty" for collection filters.  A value of false will '
                . 'be handled as though it were null.',
            self::SORT       => 'Sort the result.  Either "asc" or "desc".',
        ];
    }

    /** @param mixed[] $filterTypes */
    public function filterQueryBuilder(array $filterTypes, QueryBuilder $queryBuilder): void
    {
        foreach ($filterTypes as $field => $filters) {
            $entityField = 'entity.' . $field;

            foreach ($filters as $filter => $value) {
                switch ($filter) {
                    case self::EQ:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->eq($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::NEQ:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->neq($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::LT:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->lt($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::LTE:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->lte($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::GT:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->gt($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::GTE:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->gte($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value);
                        break;

                    case self::BETWEEN:
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

                    case self::CONTAINS:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, '%' . $value . '%');
                        break;

                    case self::STARTSWITH:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, $value . '%');
                        break;

                    case self::ENDSWITH:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->like($entityField, ':' . $parameter),
                        )
                            ->setParameter($parameter, '%' . $value);
                        break;

                    case self::IN:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->in($entityField, ':' . $parameter),
                        )
                        ->setParameter($parameter, $value);
                        break;

                    case self::NOTIN:
                        $parameter = 'p' . uniqid();
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->notIn($entityField, ':' . $parameter),
                        )
                        ->setParameter($parameter, $value);
                        break;

                    case self::ISNULL:
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

                    case self::SORT:
                        $queryBuilder->addOrderBy($entityField, $value);
                        break;
                }
            }
        }
    }
}
