<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use Attribute;

/**
 * Attribute to describe a computed field for GraphQL
 *
 * Computed fields are generated from entity methods rather than database columns.
 * They integrate with the hydrator pipeline and are cached along with regular fields.
 *
 * A computed field is filtered and sorted only when it has an expression: the
 * DQL expression of its value, which the filters compare and sort by.  Every
 * alias in it is a {placeholder}: {entity} is the entity, and any other is
 * given a unique name each time the expression is used.  {:name} is the
 * method parameter $name, whose value is given by the filter's args.
 *
 * A computed field may be on a method of the entity's repository, whose first
 * parameter is the entity.  With batch, the first parameter is a Collection of
 * entities, keyed by the database value of their identifier, and the method
 * returns the values keyed the same way, so one query gives the values of
 * every entity being resolved.
 *
 * @example
 * ```php
 * #[ComputedField(
 *     type: 'string',
 *     description: 'Full name of the user'
 * )]
 * public function getFullName(): string
 * {
 *     return $this->firstName . ' ' . $this->lastName;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ComputedField
{
    use ExcludeFilters;

    /**
     * @param string                $type           A type registered in the TypeContainer, or the class of an entity
     *                                              exposed in the group, whose type the field is
     * @param string                $group          GraphQL schema group (default: 'default')
     * @param string|null           $name           Field name in GraphQL schema. If null, derived from method name.
     * @param string|null           $description    Field description for GraphQL schema
     * @param bool                  $list           Whether the method returns a list of the type
     * @param array<string, string> $args           The registered type of each method parameter which is
     *                                              not an int, float, string or bool, by its name
     * @param string|null           $expression     The DQL expression of the field's value, by which it
     *                                              is filtered and sorted
     * @param array<Filters|string> $excludeFilters Filters cases or their values, for a field with an
     *                                              expression
     * @param array<Filters|string> $includeFilters Filters cases or their values, for a field with an
     *                                              expression
     * @param bool                  $batch          Whether a method of a repository is given a Collection
     *                                              of entities and returns their values, keyed by identifier
     */
    public function __construct(
        private readonly string $type,
        private readonly string $group = 'default',
        private readonly string|null $name = null,
        private readonly string|null $description = null,
        private readonly bool $list = false,
        private readonly array $args = [],
        private readonly string|null $expression = null,
        private readonly array $excludeFilters = [],
        private readonly array $includeFilters = [],
        private readonly bool $batch = false,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function getDescription(): string|null
    {
        return $this->description;
    }

    public function getList(): bool
    {
        return $this->list;
    }

    /** @return array<string, string> */
    public function getArgs(): array
    {
        return $this->args;
    }

    public function getExpression(): string|null
    {
        return $this->expression;
    }

    public function getBatch(): bool
    {
        return $this->batch;
    }
}
