<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Criteria;

use function implode;

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

    /**
     * Build an array suitable for QueryBuilder Applicator
     *
     * @param mixed[] $filterTypes
     *
     * @return mixed[]
     */
    public function buildQueryArray(array $filterTypes): array
    {
        $filterArray = [];

        foreach ($filterTypes as $field => $filters) {
            foreach ($filters as $filter => $value) {
                switch ($filter) {
                    case self::CONTAINS:
                        $filterArray[$field . '|like'] = $value;
                        break;
                    case self::STARTSWITH:
                        $filterArray[$field . '|startswith'] = $value;
                        break;
                    case self::ENDSWITH:
                        $filterArray[$field . '|endswith'] = $value;
                        break;
                    case self::ISNULL:
                        $filterArray[$field . '|isnull'] = 'true';
                        break;
                    case self::BETWEEN:
                        $filterArray[$field . '|between'] = $value['from'] . ',' . $value['to'];
                        break;
                    case self::IN:
                        $filterArray[$field . '|in'] = implode(',', $value);
                        break;
                    case self::NOTIN:
                        $filterArray[$field . '|notin'] = implode(',', $value);
                        break;
                    default:
                        $filterArray[$field . '|' . $filter] = (string) $value;
                        break;
                }
            }
        }

        return $filterArray;
    }
}
