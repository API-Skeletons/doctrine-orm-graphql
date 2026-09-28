<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

use function array_flip;
use function in_array;
use function is_array;
use function strcmp;
use function strtr;
use function uksort;
use function uniqid;

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

    private const array MUTABLE_TYPES = [
        Types::DATE_MUTABLE,
        Types::DATETIME_MUTABLE,
        Types::DATETIMETZ_MUTABLE,
        Types::TIME_MUTABLE,
    ];

    private const array IMMUTABLE_TYPES = [
        Types::DATE_IMMUTABLE,
        Types::DATETIME_IMMUTABLE,
        Types::DATETIMETZ_IMMUTABLE,
        Types::TIME_IMMUTABLE,
    ];

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
            $fieldType         = $this->getFieldType($queryBuilder, $entity, $field);

            foreach ($filters as $filter => $value) {
                $filter = Filters::from($filter);

                // A filter given null is not applied, as a field or filter
                // given null is not.  eq, neq, in and notin compare to null.
                if ($value === null && ! in_array($filter, [Filters::EQ, Filters::NEQ, Filters::IN, Filters::NOTIN])) {
                    continue;
                }

                $value = $this->toDatabaseValue($value, $fieldType, $queryBuilder);

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
        $this->like($field, '%' . $this->escapeLike($value) . '%', $queryBuilder);
    }

    protected function startsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $this->like($field, $this->escapeLike($value) . '%', $queryBuilder);
    }

    protected function endsWith(string $field, string $value, DoctrineQueryBuilder $queryBuilder): void
    {
        $this->like($field, '%' . $this->escapeLike($value), $queryBuilder);
    }

    /**
     * Match a LIKE pattern.  Wildcards in the value are escaped, so it matches
     * only itself.
     */
    private function like(string $field, string $pattern, DoctrineQueryBuilder $queryBuilder): void
    {
        $parameter = 'p' . uniqid();
        $queryBuilder
            ->andWhere($field . ' LIKE :' . $parameter . " ESCAPE '" . self::LIKE_ESCAPE . "'")
            ->setParameter($parameter, $pattern);
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

    /**
     * The Doctrine type of a field, or null for an association
     */
    private function getFieldType(DoctrineQueryBuilder $queryBuilder, Entity $entity, string $field): string|null
    {
        $classMetadata = $queryBuilder->getEntityManager()->getClassMetadata($entity->getEntityClass());

        return $classMetadata->hasField($field) ? $classMetadata->getTypeOfField($field) : null;
    }

    /**
     * Convert a date or time filter value to the database value of the field's
     * Doctrine type.  Bound untyped, Doctrine would bind it as a datetime,
     * which does not match a date or time column.  Arrays, as for between
     * and in, are converted element by element.
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

        if (! $value instanceof DateTimeInterface || $fieldType === null) {
            return $value;
        }

        // DBAL's date and time types accept only their own DateTimeInterface class
        if (in_array($fieldType, self::IMMUTABLE_TYPES, true)) {
            $value = DateTimeImmutable::createFromInterface($value);
        } elseif (in_array($fieldType, self::MUTABLE_TYPES, true)) {
            $value = DateTime::createFromInterface($value);
        }

        return Type::getType($fieldType)->convertToDatabaseValue(
            $value,
            $queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform(),
        );
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
