<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\ComputedFieldMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use ReflectionClass;

use function array_flip;
use function array_key_exists;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function strcmp;
use function strlen;
use function strtr;
use function uksort;

/**
 * This class is used to add filters to a Doctrine QueryBuilder based on the
 * field filters
 */
final class QueryBuilder
{
    /**
     * The LIKE escape character.  Not a backslash, which MySQL also treats as
     * an escape within the string literal.
     */
    private const string LIKE_ESCAPE = '!';

    // datetime_utc and datetime_utc_immutable are DBAL 4 types
    private const array MUTABLE_TYPES = [
        Types::DATE_MUTABLE,
        Types::DATETIME_MUTABLE,
        'datetime_utc',
        Types::DATETIMETZ_MUTABLE,
        Types::TIME_MUTABLE,
    ];

    private const array IMMUTABLE_TYPES = [
        Types::DATE_IMMUTABLE,
        Types::DATETIME_IMMUTABLE,
        'datetime_utc_immutable',
        Types::DATETIMETZ_IMMUTABLE,
        Types::TIME_IMMUTABLE,
    ];

    /**
     * The value patterns of the Doctrine types whose filter values may be
     * strings but are numbers: a bigint, decimal or number is a String, and a
     * to-one association is filtered by an ID of its target's identifier type.
     * number is a DBAL 4 type.
     */
    private const array NUMBER_PATTERNS = [
        Types::BIGINT => '/^-?[0-9]+$/',
        Types::INTEGER => '/^-?[0-9]+$/',
        Types::SMALLINT => '/^-?[0-9]+$/',
        Types::DECIMAL => '/^-?[0-9]+(\.[0-9]+)?$/',
        'number' => '/^-?[0-9]+(\.[0-9]+)?$/',
    ];

    /**
     * The least and the greatest value of the integer Doctrine types, and the
     * greatest of an unsigned column, which MySQL creates for a field with the
     * unsigned option.  A value beyond its column's range is a database error
     * on some databases, such as PostgreSQL.
     */
    private const array INTEGER_RANGES = [
        Types::SMALLINT => ['-32768', '32767', '65535'],
        Types::INTEGER => ['-2147483648', '2147483647', '4294967295'],
        Types::BIGINT => ['-9223372036854775808', '9223372036854775807', '18446744073709551615'],
    ];

    /** The filters which compare the field to a value */
    private const array COMPARISONS = [
        Filters::EQ,
        Filters::NEQ,
        Filters::LT,
        Filters::LTE,
        Filters::GT,
        Filters::GTE,
        Filters::BETWEEN,
        Filters::IN,
        Filters::NOTIN,
    ];

    /** The filter of a filter's branches, any of which a row matches */
    public const string OR = '_or';

    /** The filters which sort */
    private const array SORTS = [Filters::SORT, Filters::SORTPRIORITY];

    /**
     * The names of the types DBAL provides, keyed by name.  Any other type
     * is a custom type.
     *
     * @var array<string, true>|null
     */
    private static array|null $dbalTypes = null;

    /**
     * The sort direction and priority of each sorted field, keyed by field
     *
     * @var array<string, array{direction?: string, priority?: int}>
     */
    private array $sortFields = [];

    /**
     * The GraphQL name of each filtered field, keyed by its query builder field,
     * for errors
     *
     * @var array<string, string>
     */
    private array $fieldNames = [];

    /** The number of parameters named */
    private int $parameterCount = 0;

    /** The number of uses of computed field expressions, whose aliases are numbered by it */
    private int $expressionCount = 0;

    /**
     * @param int|null $filterDepth      How deeply _or may nest, or null when it is unlimited
     * @param int|null $filterConditions The most conditions inside _or branches, or null when it is unlimited
     */
    public function __construct(
        private readonly int|null $filterDepth = null,
        private readonly int|null $filterConditions = null,
    ) {
    }

    /**
     * Add where clauses to a QueryBuilder based on the FilterType of the entity
     *
     * @param array<string, mixed|array<string, mixed>> $filterTypes
     * @param bool                                      $sort        Whether to apply the sorts.  A count is
     *                                                               not sorted.
     *
     * @throws FilterException When a filter cannot be applied, or _or is beyond the limits.
     */
    public function apply(
        array $filterTypes,
        DoctrineQueryBuilder $queryBuilder,
        Entity $entity,
        bool $sort = true,
    ): void {
        $this->assertWithinLimits($filterTypes);

        foreach ($this->conditions($filterTypes, $queryBuilder, $entity, $sort) as $condition) {
            $queryBuilder->andWhere($condition);
        }

        $this->applySort($queryBuilder);
    }

    /**
     * The conditions of a filter, each of which a row must match: one for
     * each field filter given a value, and one for its _or
     *
     * @param array<string, mixed|array<string, mixed>> $filterTypes
     *
     * @return list<string>
     *
     * @psalm-suppress MixedAssignment, MixedArgument
     */
    private function conditions(
        array $filterTypes,
        DoctrineQueryBuilder $queryBuilder,
        Entity $entity,
        bool $sort,
    ): array {
        $conditions = [];

        foreach ($filterTypes as $fieldName => $filters) {
            if ($fieldName === self::OR) {
                if ($filters !== null) {
                    assert(is_array($filters));
                    $conditions[] = $this->orCondition($filters, $queryBuilder, $entity);
                }

                continue;
            }

            // A field given null is not applied, as a filter given null is not
            if ($filters === null) {
                continue;
            }

            // A computed field is filtered by its expression.  Each field of the
            // type has a unique name, so a computed field is not a field's alias.
            $computedField = $entity->getEntityMetadata()->computedFields[$fieldName] ?? null;
            if ($computedField instanceof ComputedFieldMetadata && $computedField->expression !== null) {
                $conditions = [
                    ...$conditions,
                    ...$this->computedFieldConditions($fieldName, $computedField, $filters, $queryBuilder, $sort),
                ];

                continue;
            }

            // Resolve aliases
            $field             = array_flip($entity->getExtractionMap())[$fieldName] ?? $fieldName;
            $queryBuilderField = 'entity.' . $field;
            $valueType         = $this->getValueType($queryBuilder, $entity, $field);
            $fieldType         = $valueType['type'];

            $this->fieldNames[$queryBuilderField] = $fieldName;

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                if (! $sort && in_array($filter, self::SORTS, true)) {
                    continue;
                }

                if (! $this->validateFilter($filter, $value, $fieldName, $fieldType, $valueType['unsigned'])) {
                    continue;
                }

                // A value compared to the field is its database value; a LIKE
                // pattern, a sort and isnull are not values of the field
                if (in_array($filter, self::COMPARISONS, true)) {
                    $value = $this->toDatabaseValue(
                        $value,
                        $fieldType,
                        $queryBuilder,
                        "Filter '" . $filter->value . "' of field '" . $fieldName . "'",
                    );
                }

                $condition = $this->condition($filter, $queryBuilderField, $value, $queryBuilder);
                if ($condition === null) {
                    continue;
                }

                $conditions[] = $condition;
            }
        }

        return $conditions;
    }

    /**
     * The condition of an _or: a row matches one of its branches, each of
     * which is a filter without sorts.  A branch which has no conditions
     * matches every row, and an _or of no branches matches none.  Each branch
     * binds its own parameters, so a branch is given a condition even when it
     * matches every row.
     *
     * @param array<array-key, mixed> $branches
     */
    private function orCondition(array $branches, DoctrineQueryBuilder $queryBuilder, Entity $entity): string
    {
        if ($branches === []) {
            return '1 = 0';
        }

        $expr  = $queryBuilder->expr();
        $parts = [];

        /** @psalm-suppress MixedAssignment Each branch is an input object */
        foreach ($branches as $branch) {
            assert(is_array($branch));

            /** @psalm-suppress MixedArgumentTypeCoercion A branch is a filter */
            $conditions = $this->conditions($branch, $queryBuilder, $entity, false);

            $parts[] = match (count($conditions)) {
                0 => '1 = 1',
                1 => $conditions[0],
                default => (string) $expr->andX(...$conditions),
            };
        }

        return count($parts) === 1 ? $parts[0] : (string) $expr->orX(...$parts);
    }

    /**
     * _or may nest at most filterDepth deep, a top-level _or being at depth
     * 1, and its branches may have at most filterConditions conditions in
     * all.  Only conditions inside _or are counted, so a filter without one is
     * never limited.  They are checked before any condition is built.
     *
     * @param array<string, mixed|array<string, mixed>> $filterTypes
     *
     * @throws FilterException
     */
    private function assertWithinLimits(array $filterTypes): void
    {
        $conditions = 0;
        $this->countBranches($filterTypes[self::OR] ?? null, 1, $conditions);

        if ($this->filterConditions !== null && $conditions > $this->filterConditions) {
            throw new FilterException(
                'A filter may have at most ' . $this->filterConditions . " conditions in '" . self::OR . "'.",
            );
        }
    }

    /**
     * Count the conditions of _or branches, the filters given a value,
     * checking the depth of each _or
     *
     * @throws FilterException
     */
    private function countBranches(mixed $branches, int $depth, int &$conditions): void
    {
        if ($branches === null) {
            return;
        }

        if ($this->filterDepth !== null && $depth > $this->filterDepth) {
            throw new FilterException(
                "A filter may nest '" . self::OR . "' at most " . $this->filterDepth . ' deep.',
            );
        }

        assert(is_array($branches));

        /** @psalm-suppress MixedAssignment Each branch is an input object */
        foreach ($branches as $branch) {
            assert(is_array($branch));

            /** @psalm-suppress MixedAssignment Each field's filters are an input object */
            foreach ($branch as $fieldName => $filters) {
                if ($fieldName === self::OR) {
                    $this->countBranches($filters, $depth + 1, $conditions);

                    continue;
                }

                if ($filters === null) {
                    continue;
                }

                assert(is_array($filters));

                /** @psalm-suppress MixedAssignment A filter's value may be of any type */
                foreach ($filters as $filter => $value) {
                    if ($filter === 'args' || $value === null) {
                        continue;
                    }

                    $conditions++;
                }
            }
        }
    }

    /**
     * The conditions of a computed field's filters, each comparing its
     * expression.  A sort orders by the expression, selected as a hidden
     * result variable, as DQL does not order by a subquery.
     *
     * @param array<string, mixed> $filters The filters, and the args of the field's arguments
     *
     * @return list<string>
     *
     * @psalm-suppress MixedAssignment, MixedArgument
     */
    private function computedFieldConditions(
        string $fieldName,
        ComputedFieldMetadata $computedField,
        array $filters,
        DoctrineQueryBuilder $queryBuilder,
        bool $sort,
    ): array {
        $args = $filters['args'] ?? [];
        $args = is_array($args) ? $args : [];
        unset($filters['args']);

        $fieldType  = self::computedFieldValueType($computedField->type);
        $sortAlias  = null;
        $conditions = [];

        foreach ($filters as $filter => $value) {
            $filter = Filters::from($filter);

            // A sort's hidden select binds the parameters of the arguments it uses
            if (! $sort && in_array($filter, self::SORTS, true)) {
                continue;
            }

            if (! $this->validateFilter($filter, $value, $fieldName, $fieldType, false)) {
                continue;
            }

            if (in_array($filter, self::SORTS, true)) {
                if ($sortAlias === null) {
                    // Named by the use of the expression, so unique in the query
                    $expression = $this->expression($computedField, $args, $queryBuilder, $fieldName);
                    $sortAlias  = 'computedSort' . $this->expressionCount;
                    $queryBuilder->addSelect($expression . ' AS HIDDEN ' . $sortAlias);

                    $this->fieldNames[$sortAlias] = $fieldName;
                }

                $this->condition($filter, $sortAlias, $value, $queryBuilder);

                continue;
            }

            if (in_array($filter, self::COMPARISONS, true)) {
                $value = $this->toDatabaseValue(
                    $value,
                    $fieldType,
                    $queryBuilder,
                    "Filter '" . $filter->value . "' of field '" . $fieldName . "'",
                );
            }

            // DQL takes no subquery for BETWEEN, so it is applied as two comparisons
            if ($filter === Filters::BETWEEN) {
                assert(is_array($value));

                $conditions[] = $this->compare(
                    Filters::GTE,
                    $this->expression($computedField, $args, $queryBuilder, $fieldName),
                    $value['from'],
                    $queryBuilder,
                );
                $conditions[] = $this->compare(
                    Filters::LTE,
                    $this->expression($computedField, $args, $queryBuilder, $fieldName),
                    $value['to'],
                    $queryBuilder,
                );

                continue;
            }

            $condition = $this->condition(
                $filter,
                $this->expression($computedField, $args, $queryBuilder, $fieldName),
                $value,
                $queryBuilder,
            );
            assert($condition !== null);

            $conditions[] = $condition;
        }

        return $conditions;
    }

    /**
     * A computed field's expression for one use: its aliases are given names
     * unique in the query, and each argument it uses is bound as a parameter,
     * of its value in the args, else its default, else null
     *
     * @param array<array-key, mixed> $args
     */
    private function expression(
        ComputedFieldMetadata $computedField,
        array $args,
        DoctrineQueryBuilder $queryBuilder,
        string $fieldName,
    ): string {
        return ComputedFieldExpression::substitute(
            (string) $computedField->expression,
            $queryBuilder->getRootAliases()[0],
            'computed' . ++$this->expressionCount . '_',
            function (string $name) use ($computedField, $args, $queryBuilder, $fieldName): string {
                $argument = $computedField->args[$name];

                /** @psalm-suppress MixedAssignment An argument may be of any type */
                $value = array_key_exists($name, $args) ? $args[$name] : $argument->default;

                $parameter = $this->parameter($queryBuilder);
                $queryBuilder->setParameter($parameter, $this->toDatabaseValue(
                    $value,
                    self::computedFieldValueType($argument->type),
                    $queryBuilder,
                    "Argument '" . $name . "' of the filter of field '" . $fieldName . "'",
                ));

                return ':' . $parameter;
            },
        );
    }

    /**
     * The Doctrine type of a computed field's values, or of an argument's: its
     * registered type, whose name is a Doctrine type's, but for int, which is
     * integer.  A type which is not a Doctrine type is bound as it is.
     */
    private static function computedFieldValueType(string $type): string|null
    {
        $type = $type === 'int' ? Types::INTEGER : $type;

        return Type::hasType($type) ? $type : null;
    }

    /**
     * Whether a filter is applied.  A filter given null is not, as a field or
     * filter given null is not, and neither is notin given an empty list.
     *
     * @throws FilterException When the value cannot be filtered by.
     */
    private function validateFilter(
        Filters $filter,
        mixed $value,
        string $fieldName,
        string|null $fieldType,
        bool $unsigned,
    ): bool {
        // eq, neq, in and notin would compare to null, which matches nothing
        if ($value === null) {
            if (in_array($filter, [Filters::EQ, Filters::NEQ, Filters::IN, Filters::NOTIN], true)) {
                throw new FilterException(
                    "Filter '" . $filter->value . "' of field '" . $fieldName . "' cannot be null.  "
                    . "Use the 'isnull' filter to match null values.",
                );
            }

            return false;
        }

        // A comparison to null matches nothing
        if (
            (in_array($filter, [Filters::IN, Filters::NOTIN], true) && is_array($value) && in_array(null, $value, true))
            || ($filter === Filters::BETWEEN && is_array($value) && (($value['from'] ?? null) === null || ($value['to'] ?? null) === null))
        ) {
            throw new FilterException(
                "Filter '" . $filter->value . "' of field '" . $fieldName . "' cannot contain null.  "
                . "Use the 'isnull' filter to match null values.",
            );
        }

        // A bigint, decimal or number is a String, and an association's ID may be
        // any string, so the value is checked to be a number.  Otherwise a
        // database may compare it, or fail to, as text.
        $pattern = $fieldType === null ? null : self::NUMBER_PATTERNS[$fieldType] ?? null;
        if (
            $pattern !== null
            && ! in_array($filter, [Filters::ISNULL, Filters::SORT, Filters::SORTPRIORITY], true)
            && ! $this->matchesNumber($value, $pattern)
        ) {
            throw new FilterException(
                "Filter '" . $filter->value . "' of field '" . $fieldName . "' must be "
                . (in_array($fieldType, [Types::BIGINT, Types::INTEGER, Types::SMALLINT], true) ? 'an integer.' : 'a number.'),
            );
        }

        // An integer must be within its column's range
        $range = $fieldType === null ? null : self::INTEGER_RANGES[$fieldType] ?? null;
        if ($range !== null && ! in_array($filter, [Filters::ISNULL, Filters::SORT, Filters::SORTPRIORITY], true)) {
            [$least, $greatest] = $unsigned ? ['0', $range[2]] : [$range[0], $range[1]];

            if (! $this->isInRange($value, $least, $greatest)) {
                throw new FilterException(
                    "Filter '" . $filter->value . "' of field '" . $fieldName . "' must be an integer from "
                    . $least . ' to ' . $greatest . '.',
                );
            }
        }

        // Every value is not in an empty list.  DBAL expands an empty list to
        // NULL, and NOT IN (NULL) matches nothing.
        return ! ($filter === Filters::NOTIN && $value === []);
    }

    /**
     * The condition of a filter, binding its parameters, or null for a sort,
     * which is recorded to order the query by
     *
     * @psalm-suppress MixedArgument The value is of the filter's GraphQL type
     */
    private function condition(
        Filters $filter,
        string $field,
        mixed $value,
        DoctrineQueryBuilder $queryBuilder,
    ): string|null {
        if ($filter === Filters::SORT) {
            $this->sort($field, $value);

            return null;
        }

        if ($filter === Filters::SORTPRIORITY) {
            $this->sortPriority($field, $value);

            return null;
        }

        return match ($filter) {
            Filters::EQ,
            Filters::NEQ,
            Filters::LT,
            Filters::LTE,
            Filters::GT,
            Filters::GTE,
            Filters::IN,
            Filters::NOTIN => $this->compare($filter, $field, $value, $queryBuilder),
            Filters::BETWEEN => $this->between($field, $value, $queryBuilder),
            Filters::CONTAINS => $this->contains($field, $value, $queryBuilder),
            Filters::STARTSWITH => $this->startsWith($field, $value, $queryBuilder),
            Filters::ENDSWITH => $this->endsWith($field, $value, $queryBuilder),
            Filters::ISNULL => $this->isnull($field, $value, $queryBuilder),
        };
    }

    /**
     * Compare the field with the value
     */
    private function compare(Filters $filter, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): string
    {
        $parameter   = $this->parameter($queryBuilder);
        $placeholder = ':' . $parameter;
        $expr        = $queryBuilder->expr();

        $queryBuilder->setParameter($parameter, $value);

        return (string) match ($filter) {
            Filters::NEQ => $expr->neq($field, $placeholder),
            Filters::LT => $expr->lt($field, $placeholder),
            Filters::LTE => $expr->lte($field, $placeholder),
            Filters::GT => $expr->gt($field, $placeholder),
            Filters::GTE => $expr->gte($field, $placeholder),
            Filters::IN => $expr->in($field, $placeholder),
            Filters::NOTIN => $expr->notIn($field, $placeholder),
            default => $expr->eq($field, $placeholder),
        };
    }

    /** @param array<string, mixed> $value */
    private function between(string $field, array $value, DoctrineQueryBuilder $queryBuilder): string
    {
        $from = $this->parameter($queryBuilder);
        $to   = $this->parameter($queryBuilder);
        $queryBuilder
            ->setParameter($from, $value['from'])
            ->setParameter($to, $value['to']);

        return $queryBuilder->expr()->between($field, ':' . $from, ':' . $to);
    }

    private function contains(string $field, string $value, DoctrineQueryBuilder $queryBuilder): string
    {
        return $this->like($field, '%' . $this->escapeLike($value) . '%', $queryBuilder);
    }

    private function startsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): string
    {
        return $this->like($field, $this->escapeLike($value) . '%', $queryBuilder);
    }

    private function endsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): string
    {
        return $this->like($field, '%' . $this->escapeLike($value), $queryBuilder);
    }

    /**
     * Match a LIKE pattern.  Wildcards in the value are escaped, so it matches
     * only itself.
     */
    private function like(string $field, string $pattern, DoctrineQueryBuilder $queryBuilder): string
    {
        $parameter = $this->parameter($queryBuilder);
        $queryBuilder->setParameter($parameter, $pattern);

        return $field . ' LIKE :' . $parameter . " ESCAPE '" . self::LIKE_ESCAPE . "'";
    }

    /**
     * A new parameter name.  A QueryBuilder event listener may have bound a
     * parameter of the same name, so a bound name is skipped.
     */
    private function parameter(DoctrineQueryBuilder $queryBuilder): string
    {
        do {
            $name = 'filter' . ++$this->parameterCount;
        } while ($queryBuilder->getParameter($name) !== null);

        return $name;
    }

    /**
     * Whether a filter value, or each value of a list or between, is an
     * integer or a string matching the pattern
     *
     * @param non-empty-string $pattern
     */
    private function matchesNumber(mixed $value, string $pattern): bool
    {
        if (is_array($value)) {
            // Filter values are GraphQL input, so their elements are mixed
            /** @psalm-suppress MixedAssignment */
            foreach ($value as $item) {
                if (! $this->matchesNumber($item, $pattern)) {
                    return false;
                }
            }

            return true;
        }

        return is_int($value) || (is_string($value) && preg_match($pattern, $value) === 1);
    }

    /**
     * Whether an integer value, or each value of a list or between, is from
     * $least to $greatest.  The values are compared as strings of digits, as
     * the greatest unsigned bigint is beyond PHP's int.
     */
    private function isInRange(mixed $value, string $least, string $greatest): bool
    {
        if (is_array($value)) {
            // Filter values are GraphQL input, so their elements are mixed
            /** @psalm-suppress MixedAssignment */
            foreach ($value as $item) {
                if (! $this->isInRange($item, $least, $greatest)) {
                    return false;
                }
            }

            return true;
        }

        // An integer type's value is an int or, as the number check found, a
        // string of digits
        $integer = is_int($value) ? (string) $value : (is_string($value) ? $value : '');

        return self::compareIntegers($integer, $least) >= 0 && self::compareIntegers($integer, $greatest) <= 0;
    }

    /**
     * Compare two integers written as strings of digits, of any length
     */
    private static function compareIntegers(string $first, string $second): int
    {
        $first  = self::normalizeInteger($first);
        $second = self::normalizeInteger($second);

        $firstNegative = str_starts_with($first, '-');
        if ($firstNegative !== str_starts_with($second, '-')) {
            return $firstNegative ? -1 : 1;
        }

        $firstDigits  = ltrim($first, '-');
        $secondDigits = ltrim($second, '-');
        $order        = strlen($firstDigits) <=> strlen($secondDigits) ?: (strcmp($firstDigits, $secondDigits) <=> 0);

        return $firstNegative ? -$order : $order;
    }

    /**
     * An integer string without leading zeros, and 0 without a sign
     */
    private static function normalizeInteger(string $integer): string
    {
        $digits = ltrim(ltrim($integer, '-'), '0');

        if ($digits === '') {
            return '0';
        }

        return (str_starts_with($integer, '-') ? '-' : '') . $digits;
    }

    /**
     * Escape the LIKE wildcards % and _, and the escape character itself
     */
    private function escapeLike(string $value): string
    {
        return strtr($value, [
            self::LIKE_ESCAPE => self::LIKE_ESCAPE . self::LIKE_ESCAPE,
            '%' => self::LIKE_ESCAPE . '%',
            '_' => self::LIKE_ESCAPE . '_',
        ]);
    }

    private function isnull(string $field, bool $value, DoctrineQueryBuilder $queryBuilder): string
    {
        return $value
            ? $queryBuilder->expr()->isNull($field)
            : $queryBuilder->expr()->isNotNull($field);
    }

    /**
     * The Doctrine type of a filter's values, of the field or of the
     * identifier of the entity a to-one association refers to, and whether
     * its column is unsigned.  The type is null for an identifier which is not
     * a field, such as of a derived identity.
     *
     * @return array{type: string|null, unsigned: bool}
     */
    private function getValueType(DoctrineQueryBuilder $queryBuilder, Entity $entity, string $field): array
    {
        $entityManager = $queryBuilder->getEntityManager();
        $metadata      = $entityManager->getClassMetadata($entity->getEntityClass());

        // An association is filtered by the identifier of the entity it refers to
        if (! $metadata->hasField($field)) {
            $metadata = $entityManager->getClassMetadata($metadata->getAssociationTargetClass($field));
            $field    = $metadata->getSingleIdentifierFieldName();

            if (! $metadata->hasField($field)) {
                return ['type' => null, 'unsigned' => false];
            }
        }

        // MySQL creates the column of a field with the unsigned option unsigned;
        // another database ignores the option
        /** @psalm-suppress MixedAssignment A field mapping's options are not typed */
        $options = $metadata->getFieldMapping($field)['options'] ?? [];

        return [
            'type' => $metadata->getTypeOfField($field),
            'unsigned' => is_array($options)
                && ($options['unsigned'] ?? false) === true
                && $entityManager->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform,
        ];
    }

    /**
     * Convert a filter value to the database value of the field's Doctrine
     * type.  Arrays, as for between and in, are converted element by element.
     *
     * A date, time or interval is converted: bound untyped, Doctrine would
     * bind a date or time as a datetime, which does not match a date or time
     * column, and could not bind an interval.
     *
     * A value of a custom type, such as a binary UUID, is converted as
     * Doctrine converts an identifier given to find(), as it may be stored in
     * another form.  For an association, the value is the identifier of the
     * entity it refers to.  The value of a type DBAL provides is bound as it
     * is.
     *
     * @param string $subject The filter or argument given the value, for an error
     *
     * @throws FilterException When a custom type cannot convert the value.
     */
    private function toDatabaseValue(
        mixed $value,
        string|null $fieldType,
        DoctrineQueryBuilder $queryBuilder,
        string $subject,
    ): mixed {
        if (is_array($value)) {
            // Filter values are GraphQL input, so their elements are mixed
            /** @psalm-suppress MixedAssignment */
            foreach ($value as $key => $item) {
                $value[$key] = $this->toDatabaseValue($item, $fieldType, $queryBuilder, $subject);
            }

            return $value;
        }

        if ($fieldType === null) {
            return $value;
        }

        $platform = $queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform();

        if ($value instanceof DateTimeInterface || $value instanceof DateInterval) {
            // DBAL's date and time types accept only their own DateTimeInterface class
            if ($value instanceof DateTimeInterface && in_array($fieldType, self::IMMUTABLE_TYPES, true)) {
                $value = DateTimeImmutable::createFromInterface($value);
            } elseif ($value instanceof DateTimeInterface && in_array($fieldType, self::MUTABLE_TYPES, true)) {
                $value = DateTime::createFromInterface($value);
            }

            return Type::getType($fieldType)->convertToDatabaseValue($value, $platform);
        }

        if (self::isDbalType($fieldType)) {
            return $value;
        }

        try {
            return Type::getType($fieldType)->convertToDatabaseValue($value, $platform);
        } catch (ConversionException) {
            throw new FilterException($subject . ' is given a value which is not valid.');
        }
    }

    /**
     * Whether a type is one DBAL provides, rather than a custom type
     */
    private static function isDbalType(string $type): bool
    {
        if (self::$dbalTypes === null) {
            /** @var array<string, string> $names Each constant of Types is a type's name */
            $names = (new ReflectionClass(Types::class))->getConstants();

            self::$dbalTypes = [];
            foreach ($names as $name) {
                self::$dbalTypes[$name] = true;
            }
        }

        return isset(self::$dbalTypes[$type]);
    }

    private function sort(string $field, string $direction): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort direction for a field
        // It will be used to apply sorting later in the applySort method.
        // The SortDirection enum guarantees the direction is ASC or DESC.
        $this->sortFields[$field]['direction'] = $direction;
    }

    private function sortPriority(string $field, int $priority): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort priority for a field
        // It will be used to apply sorting later in the applySort method
        $this->sortFields[$field]['priority'] = $priority;
    }

    private function applySort(DoctrineQueryBuilder $queryBuilder): void
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
                    . ($this->fieldNames[$field] ?? $field)
                    . "' is not set but a sortPriority was. "
                    . "Please use the 'sort' filter to set the direction.",
                );
            }

            $queryBuilder->addOrderBy($field, $sort['direction']);
        }
    }
}
