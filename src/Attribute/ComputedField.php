<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Attribute;

use Attribute;

/**
 * Attribute to describe a computed field for GraphQL
 *
 * Computed fields are generated from entity methods rather than database columns.
 * They integrate with the hydrator pipeline and are cached along with regular fields.
 *
 * Important: Computed fields cannot be filtered at the database level and will not
 * appear in filter InputObjects.
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
    /**
     * @param string      $type        GraphQL type name (required). Must match a registered type in TypeContainer.
     * @param string      $group       GraphQL schema group (default: 'default')
     * @param string|null $name        Field name in GraphQL schema. If null, derived from method name.
     * @param string|null $description Field description for GraphQL schema
     */
    public function __construct(
        private readonly string $type,
        private readonly string $group = 'default',
        private readonly string|null $name = null,
        private readonly string|null $description = null,
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
}
