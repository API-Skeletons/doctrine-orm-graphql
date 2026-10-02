<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

use function array_flip;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strcmp;
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
        foreach ($filterTypes as $fieldName => $filters) {
            // Resolve aliases
            $field             = array_flip($entity->getExtractionMap())[$fieldName] ?? $fieldName;
            $queryBuilderField = 'entity.' . $field;
            $fieldType         = $this->getFieldType($queryBuilder, $entity, $field);

            $this->fieldNames[$queryBuilderField] = $fieldName;

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                if (! $this->validateFilter($filter, $value, $fieldName, $fieldType)) {
                    continue;
                }

                $this->addFilter(
                    $filter,
                    $queryBuilderField,
                    $this->toDatabaseValue($value, $fieldType, $queryBuilder),
                    $queryBuilder,
                );
            }
        }

        $this->applySort($queryBuilder);
    }

    /**
     * Whether a filter is applied.  A filter given null is not, as a field or
     * filter given null is not, and neither is notin given an empty list.
     *
     * @throws FilterException When the value cannot be filtered by.
     */
    private function validateFilter(Filters $filter, mixed $value, string $fieldName, string|null $fieldType): bool
    {
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

        // Every value is not in an empty list.  DBAL expands an empty list to
        // NULL, and NOT IN (NULL) matches nothing.
        return ! ($filter === Filters::NOTIN && $value === []);
    }

    /**
     * Add a filter to the QueryBuilder
     *
     * @psalm-suppress MixedArgument The value is of the filter's GraphQL type
     */
    private function addFilter(Filters $filter, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): void
    {
        match ($filter) {
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
            Filters::SORT => $this->sort($field, $value, $queryBuilder),
            Filters::SORTPRIORITY => $this->sortPriority($field, $value, $queryBuilder),
        };
    }

    /**
     * Compare the field with the value
     */
    private function compare(Filters $filter, string $field, mixed $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter   = $this->parameter($queryBuilder);
        $placeholder = ':' . $parameter;
        $expr        = $queryBuilder->expr();

        $queryBuilder
            ->andWhere(match ($filter) {
                Filters::NEQ => $expr->neq($field, $placeholder),
                Filters::LT => $expr->lt($field, $placeholder),
                Filters::LTE => $expr->lte($field, $placeholder),
                Filters::GT => $expr->gt($field, $placeholder),
                Filters::GTE => $expr->gte($field, $placeholder),
                Filters::IN => $expr->in($field, $placeholder),
                Filters::NOTIN => $expr->notIn($field, $placeholder),
                default => $expr->eq($field, $placeholder),
            })
            ->setParameter($parameter, $value);
    }

    /** @param array<string, mixed> $value */
    private function between(string $field, array $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $from = $this->parameter($queryBuilder);
        $to   = $this->parameter($queryBuilder);
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

    private function contains(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $this->like($field, '%' . $this->escapeLike($value) . '%', $queryBuilder);
    }

    private function startsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $this->like($field, $this->escapeLike($value) . '%', $queryBuilder);
    }

    private function endsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $this->like($field, '%' . $this->escapeLike($value), $queryBuilder);
    }

    /**
     * Match a LIKE pattern.  Wildcards in the value are escaped, so it matches
     * only itself.
     */
    private function like(string $field, string $pattern, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = $this->parameter($queryBuilder);
        $queryBuilder
            ->andWhere($field . ' LIKE :' . $parameter . " ESCAPE '" . self::LIKE_ESCAPE . "'")
            ->setParameter($parameter, $pattern);
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

    private function isnull(string $field, bool $value, DoctrineQueryBuilder $queryBuilder): void
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

    /**
     * The Doctrine type of a filter's values: of the field, or of the
     * identifier of the entity a to-one association refers to.  Null for an
     * identifier which is not a field, such as of a derived identity.
     */
    private function getFieldType(DoctrineQueryBuilder $queryBuilder, Entity $entity, string $field): string|null
    {
        $entityManager = $queryBuilder->getEntityManager();
        $classMetadata = $entityManager->getClassMetadata($entity->getEntityClass());

        if ($classMetadata->hasField($field)) {
            return $classMetadata->getTypeOfField($field);
        }

        // An association is filtered by the identifier of the entity it refers to
        $targetMetadata = $entityManager->getClassMetadata($classMetadata->getAssociationTargetClass($field));
        $identifier     = $targetMetadata->getSingleIdentifierFieldName();

        return $targetMetadata->hasField($identifier) ? $targetMetadata->getTypeOfField($identifier) : null;
    }

    /**
     * Convert a date, time or interval filter value to the database value of
     * the field's Doctrine type.  Bound untyped, Doctrine would bind a date or
     * time as a datetime, which does not match a date or time column, and could
     * not bind an interval.  Arrays, as for between and in, are converted
     * element by element.
     */
    private function toDatabaseValue(mixed $value, string|null $fieldType, DoctrineQueryBuilder $queryBuilder): mixed
    {
        if (is_array($value)) {
            // Filter values are GraphQL input, so their elements are mixed
            /** @psalm-suppress MixedAssignment */
            foreach ($value as $key => $item) {
                $value[$key] = $this->toDatabaseValue($item, $fieldType, $queryBuilder);
            }

            return $value;
        }

        if ((! $value instanceof DateTimeInterface && ! $value instanceof DateInterval) || $fieldType === null) {
            return $value;
        }

        // DBAL's date and time types accept only their own DateTimeInterface class
        if ($value instanceof DateTimeInterface && in_array($fieldType, self::IMMUTABLE_TYPES, true)) {
            $value = DateTimeImmutable::createFromInterface($value);
        } elseif ($value instanceof DateTimeInterface && in_array($fieldType, self::MUTABLE_TYPES, true)) {
            $value = DateTime::createFromInterface($value);
        }

        return Type::getType($fieldType)->convertToDatabaseValue(
            $value,
            $queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform(),
        );
    }

    private function sort(string $field, string $direction, DoctrineQueryBuilder $queryBuilder): void
    {
        if (! isset($this->sortFields[$field])) {
            $this->sortFields[$field] = [];
        }

        // This method is used to set the sort direction for a field
        // It will be used to apply sorting later in the applySort method.
        // The SortDirection enum guarantees the direction is ASC or DESC.
        $this->sortFields[$field]['direction'] = $direction;
    }

    private function sortPriority(string $field, int $priority, DoctrineQueryBuilder $queryBuilder): void
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
