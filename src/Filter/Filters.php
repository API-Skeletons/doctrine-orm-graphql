<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Between;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\SortDirection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

use function array_map;
use function array_values;
use function assert;
use function is_string;

/**
 * This handles all available filters
 */
enum Filters: string
{
    case EQ           = 'eq';
    case NEQ          = 'neq';
    case LT           = 'lt';
    case LTE          = 'lte';
    case GT           = 'gt';
    case GTE          = 'gte';
    case BETWEEN      = 'between';
    case CONTAINS     = 'contains';
    case STARTSWITH   = 'startswith';
    case ENDSWITH     = 'endswith';
    case IN           = 'in';
    case NOTIN        = 'notin';
    case ISNULL       = 'isnull';
    case SORT         = 'sort';
    case SORTPRIORITY = 'sortPriority';

    /**
     * Fetch the description for the filter
     */
    public function description(): string
    {
        return match ($this) {
            self::EQ           => 'Equals',
            self::NEQ          => 'Not equals',
            self::LT           => 'Less than',
            self::LTE          => 'Less than or equals',
            self::GT           => 'Greater than',
            self::GTE          => 'Greater than or equals',
            self::BETWEEN      => 'Is between from and to inclusive of from and to',
            self::CONTAINS     => 'Contains the value.  Strings only.',
            self::STARTSWITH   => 'Starts with the value.  Strings only.',
            self::ENDSWITH     => 'Ends with the value.  Strings only.',
            self::IN           => 'In the array of values',
            self::NOTIN        => 'Not in the array of values',
            self::ISNULL       => 'Is null',
            self::SORT         => 'Sort by field.  ASC or DESC.',
            self::SORTPRIORITY => 'Specify the sort priority of a field.   Priorities are sorted lowest number first.  Sort must also be speciifed.',
        };
    }

    /**
     * Fetch the GraphQL type for the filter of a field of the scalar type
     */
    public function type(ScalarType $type, TypeContainer $typeContainer): Type
    {
        return match ($this) {
            self::EQ           => $type,
            self::NEQ          => $type,
            self::LT           => $type,
            self::LTE          => $type,
            self::GT           => $type,
            self::GTE          => $type,
            self::BETWEEN      => $this->between($type, $typeContainer),
            self::CONTAINS     => $type,
            self::STARTSWITH   => $type,
            self::ENDSWITH     => $type,
            self::IN           => Type::listOf($type),
            self::NOTIN        => Type::listOf($type),
            self::ISNULL       => Type::boolean(),
            self::SORT         => $this->sortDirection($typeContainer),
            self::SORTPRIORITY => Type::int(),
        };
    }

    /**
     * The Between type of a scalar type is shared through the TypeContainer
     * because a schema may contain only one type of each name
     */
    private function between(ScalarType $type, TypeContainer $typeContainer): Between
    {
        $name = 'Between_' . $type->name();

        if (! $typeContainer->has($name)) {
            $typeContainer->set($name, new Between($type));
        }

        $between = $typeContainer->get($name);
        assert($between instanceof Between);

        return $between;
    }

    /**
     * The SortDirection enum is shared through the TypeContainer because a
     * schema may contain only one type of each name
     */
    private function sortDirection(TypeContainer $typeContainer): SortDirection
    {
        $sortDirection = $typeContainer->get('sortdirection');
        assert($sortDirection instanceof SortDirection);

        return $sortDirection;
    }

    /**
     * Convert an array of Filters or strings to an array of Filters
     *
     * @param array<string>|Filters[] $filters
     *
     * @return Filters[]
     */
    public static function fromArray(array $filters): array
    {
        $filters = array_map(
            static function ($filter) {
                return is_string($filter) ? Filters::from($filter) : $filter;
            },
            $filters,
        );

        return $filters;
    }

    /**
     * Convert an array of enum values to a list of strings.  The filters may
     * come from array_udiff() or array_uintersect(), which keep their keys, so
     * the result is re-indexed.
     *
     * @param Filters[] $filters
     *
     * @return list<string>
     */
    public static function toStringArray(array $filters): array
    {
        return array_values(array_map(
            static function (Filters $filter) {
                return $filter->value;
            },
            $filters,
        ));
    }
}
