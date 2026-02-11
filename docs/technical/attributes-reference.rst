====================
Attributes Reference
====================

This document provides comprehensive reference documentation for all PHP attributes used to configure GraphQL exposure of Doctrine entities.

Overview
========

The library uses PHP 8 attributes to declaratively configure which entities, fields, and associations are exposed via GraphQL. Four main attributes are available:

- ``#[Entity]`` - Marks a Doctrine entity for GraphQL exposure
- ``#[Field]`` - Exposes an entity field (scalar property)
- ``#[Association]`` - Exposes an entity association (relationship)
- ``#[ComputedField]`` - Exposes derived values from entity methods

All attributes are in the ``ApiSkeletons\Doctrine\ORM\GraphQL\Attribute`` namespace.

**Recommended Import**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

Attribute Characteristics
==========================

Repeatable Attributes
---------------------

All four attributes are **repeatable**, allowing multiple configurations per entity/field/method:

.. code-block:: php

    #[GraphQL\Entity(group: 'public')]
    #[GraphQL\Entity(group: 'admin')]
    class Artist
    {
        #[GraphQL\Field(group: 'public')]
        #[GraphQL\Field(group: 'admin', description: 'Artist ID for admins')]
        private int $id;
    }

**Use Cases**:

- Multiple API versions (v1, v2)
- Different access levels (public, admin, internal)
- Separate schemas for different clients

Group Filtering
---------------

The Driver loads only attributes matching the configured group:

.. code-block:: php

    // Only loads 'public' attributes
    $publicDriver = new Driver($em, new Config(['group' => 'public']));

    // Only loads 'admin' attributes
    $adminDriver = new Driver($em, new Config(['group' => 'admin']));

Entity Attribute
================

Marks a Doctrine entity class for GraphQL exposure.

**Target**: ``Attribute::TARGET_CLASS``

**Repeatable**: Yes

Signature
---------

.. code-block:: php

    #[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
    final class Entity
    {
        public function __construct(
            string $group = 'default',
            bool $byValue = true,
            int $limit = 0,
            ?string $description = null,
            ?string $typeName = null,
            array $excludeFilters = [],
            array $includeFilters = [],
        );
    }

Parameters
----------

group
^^^^^

**Type**: ``string``

**Default**: ``'default'``

**Description**: The configuration group this attribute belongs to.

**Example**:

.. code-block:: php

    #[GraphQL\Entity(group: 'public')]
    #[GraphQL\Entity(group: 'admin')]
    class Artist { }

    // Load only 'public' group
    $driver = new Driver($em, new Config(['group' => 'public']));

byValue
^^^^^^^

**Type**: ``bool``

**Default**: ``true``

**Description**: Controls Doctrine Laminas Hydrator extraction mode.

- ``true``: Extract by value (uses getters, creates deep copies)
- ``false``: Extract by reference (direct property access, preserves lazy loading)

**Example**:

.. code-block:: php

    // Extract using getters
    #[GraphQL\Entity(byValue: true)]
    class Artist
    {
        public function getName(): string
        {
            return strtoupper($this->name);  // Transformation applied
        }
    }

    // Extract directly from properties
    #[GraphQL\Entity(byValue: false)]
    class Performance
    {
        private string $venue;  // Extracted directly
    }

**Override**: Config ``globalByValue`` setting overrides this parameter.

**Performance**: ``byValue: false`` is faster but bypasses getters.

limit
^^^^^

**Type**: ``int``

**Default**: ``0`` (uses Config limit)

**Description**: Maximum results returned for queries on this entity.

**Example**:

.. code-block:: php

    // Limit to 100 results
    #[GraphQL\Entity(limit: 100)]
    class Artist { }

    // Limit to 1000 results
    #[GraphQL\Entity(limit: 1000)]
    class Performance { }

**Priority**: Association limit > Entity limit > Config limit

**Use Case**: Prevent expensive queries on large entities.

description
^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Human-readable description for GraphQL schema documentation.

**Example**:

.. code-block:: php

    #[GraphQL\Entity(description: 'Musical artists and bands')]
    class Artist { }

**Introspection**:

.. code-block:: graphql

    query {
        __type(name: "Artist") {
            description
        }
    }
    # Returns: "Musical artists and bands"

typeName
^^^^^^^^

**Type**: ``string|null``

**Default**: ``null`` (auto-generated from class name)

**Description**: Override the GraphQL type name.

**Auto-Generated Format**:

.. code-block:: text

    NamespacePrefix_ClassName_GroupSuffix

**Example**:

.. code-block:: php

    namespace App\Entity;

    // Default type name: App_Entity_Artist_default
    #[GraphQL\Entity]
    class Artist { }

    // Custom type name: MusicArtist_default
    #[GraphQL\Entity(typeName: 'MusicArtist')]
    class Artist { }

**Simplification**:

.. code-block:: php

    // Clean type names with config
    new Config([
        'entityPrefix' => 'App\\Entity\\',  // Remove namespace
        'groupSuffix' => '',                // Remove suffix
    ]);

    // Type name: Artist (clean!)

excludeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Array of filter types to exclude for all fields and associations in this entity.

**Example**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    // Exclude expensive LIKE filters
    #[GraphQL\Entity(excludeFilters: [
        Filters::CONTAINS,
        Filters::STARTSWITH,
        Filters::ENDSWITH,
    ])]
    class Artist { }

**Effect**: These filters will not be available for any field or association in this entity.

**Additive**: Combined with field-level and config-level exclusions.

includeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Array of filter types to explicitly include (whitelist approach).

**Example**:

.. code-block:: php

    // Only allow equality filters
    #[GraphQL\Entity(includeFilters: [
        Filters::EQ,
        Filters::NEQ,
        Filters::ISNULL,
    ])]
    class Artist { }

**Mutually Exclusive**: Cannot use both ``excludeFilters`` and ``includeFilters`` on the same attribute.

**Use Case**: Restrictive APIs that only allow specific filter types.

Field Attribute
===============

Exposes an entity field (scalar property) in GraphQL.

**Target**: ``Attribute::TARGET_PROPERTY``

**Repeatable**: Yes

Signature
---------

.. code-block:: php

    #[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
    final class Field
    {
        public function __construct(
            string $group = 'default',
            ?string $alias = null,
            ?string $description = null,
            ?string $type = null,
            ?string $hydratorStrategy = null,
            array $excludeFilters = [],
            array $includeFilters = [],
        );
    }

Parameters
----------

group
^^^^^

**Type**: ``string``

**Default**: ``'default'``

**Description**: Configuration group for this field.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Field(group: 'public')]
        #[GraphQL\Field(group: 'admin')]
        private int $id;

        #[GraphQL\Field(group: 'admin')]  // Admin-only field
        private string $email;
    }

alias
^^^^^

**Type**: ``string|null``

**Default**: ``null`` (uses property name)

**Description**: Alternative name for the field in GraphQL schema.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Field(alias: 'artistName')]
        private string $name;

        #[GraphQL\Field(alias: 'artistId')]
        private int $id;
    }

**GraphQL Query**:

.. code-block:: graphql

    query {
        artists {
            edges {
                node {
                    artistId      # Instead of 'id'
                    artistName    # Instead of 'name'
                }
            }
        }
    }

**Use Cases**:

- Avoid naming conflicts
- Match external API conventions
- Maintain backward compatibility during refactoring

description
^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Human-readable field description for schema documentation.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Field(description: 'Unique identifier for the artist')]
        private int $id;

        #[GraphQL\Field(description: 'Full name or band name')]
        private string $name;
    }

type
^^^^

**Type**: ``string|null``

**Default**: ``null`` (auto-detected from Doctrine mapping)

**Description**: Override the GraphQL type for this field.

**Auto-Detection**:

.. code-block:: php

    private int $id;        // -> Int
    private string $name;   // -> String
    private bool $active;   // -> Boolean
    private float $rating;  // -> Float

**Custom Type**:

.. code-block:: php

    class Artist
    {
        // Treat int as string in GraphQL
        #[GraphQL\Field(type: 'string')]
        private int $legacyId;
    }

    // Register custom type
    $driver->get(TypeContainer::class)->set('string', Type::string());

**Use Cases**:

- Custom scalar types (Email, URL, JSON)
- Override Doctrine type mapping
- Polymorphic field types

hydratorStrategy
^^^^^^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Fully-qualified class name of a custom hydration strategy.

**Example**:

.. code-block:: php

    use App\GraphQL\Strategy\UpperCaseStrategy;

    class Artist
    {
        #[GraphQL\Field(hydratorStrategy: UpperCaseStrategy::class)]
        private string $name;
    }

**Strategy Implementation**:

.. code-block:: php

    use Laminas\Hydrator\Strategy\StrategyInterface;

    class UpperCaseStrategy implements StrategyInterface
    {
        public function extract($value, ?object $object = null): mixed
        {
            return strtoupper($value);
        }

        public function hydrate($value, ?array $data = null): mixed
        {
            return $value;
        }
    }

**Registration**: Strategy must be registered in HydratorContainer.

**Use Cases**:

- Data transformation (formatting, normalization)
- Computed fields
- Field concatenation

excludeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Exclude specific filters for this field.

**Example**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    class Artist
    {
        // No LIKE filters on name
        #[GraphQL\Field(excludeFilters: [
            Filters::CONTAINS,
            Filters::STARTSWITH,
            Filters::ENDSWITH,
        ])]
        private string $name;

        // No range filters on rating
        #[GraphQL\Field(excludeFilters: [
            Filters::LT,
            Filters::LTE,
            Filters::GT,
            Filters::GTE,
        ])]
        private int $rating;
    }

**Additive**: Combined with entity-level and config-level exclusions.

includeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Explicitly include only specific filters (whitelist).

**Example**:

.. code-block:: php

    // Only equality checks on status
    #[GraphQL\Field(includeFilters: [
        Filters::EQ,
        Filters::NEQ,
    ])]
    private string $status;

**Mutually Exclusive**: Cannot use with ``excludeFilters`` on same attribute.

Association Attribute
=====================

Exposes an entity association (relationship) in GraphQL.

**Target**: ``Attribute::TARGET_PROPERTY``

**Repeatable**: Yes

Signature
---------

.. code-block:: php

    #[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
    final class Association
    {
        public function __construct(
            string $group = 'default',
            ?string $alias = null,
            ?string $description = null,
            ?int $limit = null,
            ?string $criteriaEventName = null,
            ?string $hydratorStrategy = null,
            array $excludeFilters = [],
            array $includeFilters = [],
        );
    }

Parameters
----------

group
^^^^^

**Type**: ``string``

**Default**: ``'default'``

**Description**: Configuration group for this association.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Association(group: 'public')]
        #[GraphQL\Association(group: 'admin', alias: 'shows')]
        private Collection $performances;
    }

alias
^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Alternative name for the association in GraphQL.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Association(alias: 'shows')]
        private Collection $performances;

        #[GraphQL\Association(alias: 'albums')]
        private Collection $recordings;
    }

**GraphQL**:

.. code-block:: graphql

    query {
        artists {
            edges {
                node {
                    shows {     # Instead of 'performances'
                        edges { node { venue } }
                    }
                    albums {    # Instead of 'recordings'
                        edges { node { title } }
                    }
                }
            }
        }
    }

description
^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Human-readable association description.

**Example**:

.. code-block:: php

    #[GraphQL\Association(description: 'All performances by this artist')]
    private Collection $performances;

limit
^^^^^

**Type**: ``int|null``

**Default**: ``null``

**Description**: Maximum results for this specific association.

**Example**:

.. code-block:: php

    class Artist
    {
        // Limit to 100 performances per artist
        #[GraphQL\Association(limit: 100)]
        private Collection $performances;

        // Limit to 10 recordings per artist
        #[GraphQL\Association(limit: 10)]
        private Collection $recordings;
    }

**Priority**: Association limit > Entity limit > Config limit

**Highest Priority**: This is the most specific limit and overrides all others.

criteriaEventName
^^^^^^^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Custom event name for QueryBuilder events when resolving this association.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\Association(
            criteriaEventName: 'artist.performances.query'
        )]
        private Collection $performances;
    }

**Event Listener**:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.performances.query',
        function (QueryBuilderEvent $event) {
            // Add custom filtering
            $event->getQueryBuilder()
                ->andWhere('entity.isDeleted = false')
                ->andWhere('entity.date > :minDate')
                ->setParameter('minDate', '2020-01-01');
        }
    );

**Use Cases**:

- Soft delete filtering
- User-specific filtering
- Date range restrictions
- Status filtering

hydratorStrategy
^^^^^^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Custom hydration strategy for the association.

**Example**:

.. code-block:: php

    use App\GraphQL\Strategy\LimitedCollectionStrategy;

    #[GraphQL\Association(
        hydratorStrategy: LimitedCollectionStrategy::class
    )]
    private Collection $performances;

**Use Cases**:

- Custom collection transformations
- Computed association data
- Aggregations

excludeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Exclude filters for this association's target entity.

**Example**:

.. code-block:: php

    class Artist
    {
        // No LIKE filters on performance fields
        #[GraphQL\Association(excludeFilters: [
            Filters::CONTAINS,
            Filters::STARTSWITH,
            Filters::ENDSWITH,
        ])]
        private Collection $performances;
    }

**Effect**: Excluded filters apply to all fields of the target entity (Performance) when accessed through this association.

includeFilters
^^^^^^^^^^^^^^

**Type**: ``Filters[]``

**Default**: ``[]``

**Description**: Whitelist specific filters for this association.

**Example**:

.. code-block:: php

    // Only allow simple filters
    #[GraphQL\Association(includeFilters: [
        Filters::EQ,
        Filters::NEQ,
    ])]
    private Collection $performances;

**Mutually Exclusive**: Cannot use with ``excludeFilters``.

ComputedField Attribute
=======================

Exposes computed values from entity methods in GraphQL schema.

**Target**: ``Attribute::TARGET_METHOD``

**Repeatable**: Yes

Signature
---------

.. code-block:: php

    #[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
    final class ComputedField
    {
        public function __construct(
            string $type,
            string $group = 'default',
            ?string $name = null,
            ?string $description = null,
        );
    }

Parameters
----------

type
^^^^

**Type**: ``string``

**Required**: Yes

**Description**: The GraphQL type name for the computed field.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\ComputedField(type: 'string')]
        public function getFullName(): string
        {
            return $this->firstName . ' ' . $this->lastName;
        }

        #[GraphQL\ComputedField(type: 'int')]
        public function getAge(): int
        {
            return (new DateTime())->diff($this->birthDate)->y;
        }

        #[GraphQL\ComputedField(type: 'boolean')]
        public function isActive(): bool
        {
            return $this->status === 'active';
        }
    }

**Type Registration**: The type must be registered in TypeContainer. Built-in types (string, int, boolean, float) are pre-registered.

**Custom Types**:

.. code-block:: php

    // Register custom type
    $driver->get(TypeContainer::class)->set('email', new EmailType());

    // Use in computed field
    #[GraphQL\ComputedField(type: 'email')]
    public function getContactEmail(): string
    {
        return $this->email;
    }

group
^^^^^

**Type**: ``string``

**Default**: ``'default'``

**Description**: Configuration group for this computed field.

**Example**:

.. code-block:: php

    class Artist
    {
        #[GraphQL\ComputedField(type: 'string', group: 'public')]
        #[GraphQL\ComputedField(type: 'string', group: 'admin')]
        public function getFullName(): string
        {
            return $this->firstName . ' ' . $this->lastName;
        }

        // Admin-only computed field
        #[GraphQL\ComputedField(type: 'string', group: 'admin')]
        public function getInternalNotes(): string
        {
            return $this->notes;
        }
    }

name
^^^^

**Type**: ``string|null``

**Default**: ``null`` (auto-derived from method name)

**Description**: Override the GraphQL field name.

**Auto-Derivation Rules**:

.. code-block:: php

    getFullName()     -> fullName
    getEmailDomain()  -> emailDomain
    isActive()        -> isActive (preserved for boolean methods)
    calculateTotal()  -> calculateTotal (fallback: method name as-is)

**Custom Name**:

.. code-block:: php

    #[GraphQL\ComputedField(
        type: 'string',
        name: 'displayName'
    )]
    public function getFullDisplayName(): string
    {
        return 'Artist: ' . $this->name;
    }

**GraphQL Query**:

.. code-block:: graphql

    query {
        artists {
            edges {
                node {
                    displayName    # Not 'fullDisplayName'
                }
            }
        }
    }

description
^^^^^^^^^^^

**Type**: ``string|null``

**Default**: ``null``

**Description**: Human-readable description for schema documentation.

**Example**:

.. code-block:: php

    #[GraphQL\ComputedField(
        type: 'string',
        description: 'Full name combining first and last name'
    )]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

**Introspection**:

.. code-block:: graphql

    query {
        __type(name: "Artist") {
            fields {
                name
                description
            }
        }
    }

How Computed Fields Work
-------------------------

**Extraction Pipeline**

1. **Metadata Extraction**: MetadataFactory scans entity methods for ``#[ComputedField]`` attributes
2. **Hydrator Registration**: DoctrineObjectWithComputed hydrator registers extraction closures
3. **Type Building**: Entity type adds computed fields to GraphQL type definition
4. **Resolution**: FieldResolver extracts computed values using hydrator
5. **Caching**: Values cached per request if ``useHydratorCache`` enabled

**Implementation Details**:

.. code-block:: php

    // Internal: How computed fields are registered in the hydrator
    $hydrator->addComputedField(
        $fieldName,
        static fn ($entity) => $entity->getFullName()
    );

    // When extracted, the hydrator calls the method
    $data = $hydrator->extract($artist);
    // $data['fullName'] = $artist->getFullName()

**Performance**: Computed fields are only evaluated when explicitly requested in GraphQL queries (lazy evaluation).

Characteristics
---------------

**Integrated Extraction**

Computed fields are extracted alongside regular fields:

.. code-block:: php

    $artist = $entityManager->find(Artist::class, 1);
    $hydrator = $driver->get(HydratorContainer::class)->get(Artist::class);

    $data = $hydrator->extract($artist);
    // [
    //     'id' => 1,
    //     'firstName' => 'Jerry',
    //     'lastName' => 'Garcia',
    //     'fullName' => 'Jerry Garcia',  // Computed
    // ]

**No Database Filtering**

Computed fields cannot be filtered at database level:

.. code-block:: php

    $filterType = $driver->filter(Artist::class);
    $fields = $filterType->getFields();

    // Computed fields NOT present
    isset($fields['fullName']);  // false

    // Regular fields present
    isset($fields['firstName']); // true

**Reason**: Computed values are calculated in PHP after data retrieval, not in SQL.

**Request-Scoped Caching**

.. code-block:: php

    $driver = new Driver($em, new Config([
        'useHydratorCache' => true,
    ]));

    // First query extracts and caches
    $result1 = GraphQL::executeQuery($schema, $query1);

    // Second query uses cached extraction
    $result2 = GraphQL::executeQuery($schema, $query2);

**Cache Scope**: Per-request only. Cleared after each GraphQL execution.

Use Cases
---------

**1. Simple Concatenation**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'string')]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

**2. Calculations**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'float', description: 'Total with tax')]
    public function getTotalWithTax(): float
    {
        return $this->subtotal * (1 + $this->taxRate);
    }

**3. Formatting**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'string')]
    public function getFormattedDate(): string
    {
        return $this->createdAt->format('Y-m-d H:i:s');
    }

**4. Business Logic**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'boolean')]
    public function isEligibleForDiscount(): bool
    {
        return $this->memberSince < new DateTime('-1 year')
            && $this->totalPurchases > 1000;
    }

**5. String Manipulation**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'string')]
    public function getEmailDomain(): string
    {
        return substr($this->email, strpos($this->email, '@') + 1);
    }

**6. Collection Aggregation**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'int')]
    public function getPerformanceCount(): int
    {
        return $this->performances->count();
    }

Limitations
-----------

**No Complex Queries**

Computed fields should NOT execute database queries:

.. code-block:: php

    // BAD: N+1 query problem
    #[GraphQL\ComputedField(type: 'int')]
    public function getPerformanceCount(): int
    {
        // This triggers a database query per artist!
        return $entityManager->createQueryBuilder()
            ->select('COUNT(p)')
            ->from(Performance::class, 'p')
            ->where('p.artist = :artist')
            ->setParameter('artist', $this)
            ->getQuery()
            ->getSingleScalarResult();
    }

**Better Alternative**: Use EntityDefinition event for database-dependent computed fields.

**No Filtering**

Cannot filter by computed fields:

.. code-block:: graphql

    # This WON'T work - fullName not in filters
    query {
        artists(filter: { fullName: { eq: "Jerry Garcia" } }) {
            edges { node { fullName } }
        }
    }

**Alternative**: Store filterable values in database or use QueryBuilder event.

**Method Requirements**

Methods must be:

- Public
- Non-static
- Not constructors
- Return a value

.. code-block:: php

    // VALID
    public function getFullName(): string { }

    // INVALID: private
    private function getFullName(): string { }

    // INVALID: static
    public static function getFullName(): string { }

    // INVALID: constructor
    public function __construct() { }

Best Practices
--------------

**1. Keep Computations Simple**

.. code-block:: php

    // GOOD: Simple, fast computation
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // BAD: Complex, slow computation
    public function getComplexCalculation(): float
    {
        // Avoid expensive operations
        foreach ($this->items as $item) {
            // Complex nested logic...
        }
    }

**2. Use Type Hints**

.. code-block:: php

    // GOOD: Clear return type
    public function getAge(): int
    {
        return (new DateTime())->diff($this->birthDate)->y;
    }

    // BAD: No type hint
    public function getAge()
    {
        return (new DateTime())->diff($this->birthDate)->y;
    }

**3. Document Complex Logic**

.. code-block:: php

    /**
     * Calculate eligibility based on membership duration and purchase history
     */
    #[GraphQL\ComputedField(
        type: 'boolean',
        description: 'Whether user qualifies for premium discount'
    )]
    public function isPremiumEligible(): bool
    {
        return $this->memberSince < new DateTime('-1 year')
            && $this->totalPurchases > 1000;
    }

**4. Avoid Side Effects**

.. code-block:: php

    // BAD: Modifies state
    public function getAndIncrementCounter(): int
    {
        return $this->counter++;  // Don't do this!
    }

    // GOOD: Pure function
    public function getCounter(): int
    {
        return $this->counter;
    }

**5. Use Appropriate Types**

.. code-block:: php

    // Match return type to GraphQL type
    #[GraphQL\ComputedField(type: 'string')]
    public function getStatus(): string { }  // Good

    #[GraphQL\ComputedField(type: 'int')]
    public function getStatus(): string { }  // Bad: type mismatch

Common Patterns
---------------

**Pattern 1: Full Name**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'string')]
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

**Pattern 2: Age from Date**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'int')]
    public function getAge(): int
    {
        return (new DateTime())->diff($this->birthDate)->y;
    }

**Pattern 3: Status Check**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'boolean')]
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expiresAt > new DateTime();
    }

**Pattern 4: Formatted Money**

.. code-block:: php

    #[GraphQL\ComputedField(type: 'string')]
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }

**Pattern 5: Multiple Groups**

.. code-block:: php

    // Public: basic info
    #[GraphQL\ComputedField(type: 'string', group: 'public')]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // Admin: detailed info
    #[GraphQL\ComputedField(
        type: 'string',
        group: 'admin',
        description: 'Full name with ID'
    )]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName . ' (#' . $this->id . ')';
    }

Comparison with Events
----------------------

**When to use ComputedField attribute**:

- Simple calculations
- String concatenation/formatting
- Boolean logic
- Collection counts
- No database queries needed

**When to use EntityDefinition event**:

- Complex database queries
- Aggregations requiring SQL
- Fields needing custom resolve logic
- Dynamic field addition based on runtime conditions

Complete Example
================

Comprehensive example showing all attributes and parameters:

.. code-block:: php

    <?php

    namespace App\Entity;

    use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
    use Doctrine\Common\Collections\Collection;
    use Doctrine\ORM\Mapping as ORM;

    /**
     * Artist entity with multiple GraphQL configurations
     */
    #[ORM\Entity]
    #[GraphQL\Entity(
        group: 'public',
        description: 'Musical artists and bands',
        typeName: 'PublicArtist',
        limit: 100,
        byValue: true,
        excludeFilters: [Filters::CONTAINS],
    )]
    #[GraphQL\Entity(
        group: 'admin',
        description: 'Artist data with admin fields',
        typeName: 'AdminArtist',
        limit: 1000,
        byValue: true,
    )]
    class Artist
    {
        /**
         * Primary key
         */
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        #[ORM\GeneratedValue]
        #[GraphQL\Field(
            group: 'public',
            description: 'Unique identifier',
        )]
        #[GraphQL\Field(
            group: 'admin',
            description: 'Primary key for database',
        )]
        private int $id;

        /**
         * First name
         */
        #[ORM\Column(type: 'string')]
        #[GraphQL\Field(
            group: 'public',
            description: 'Artist first name',
        )]
        #[GraphQL\Field(group: 'admin')]
        private string $firstName;

        /**
         * Last name
         */
        #[ORM\Column(type: 'string')]
        #[GraphQL\Field(
            group: 'public',
            description: 'Artist last name',
        )]
        #[GraphQL\Field(group: 'admin')]
        private string $lastName;

        /**
         * Email address (admin only)
         */
        #[ORM\Column(type: 'string')]
        #[GraphQL\Field(
            group: 'admin',
            description: 'Contact email',
            includeFilters: [Filters::EQ],
        )]
        private string $email;

        /**
         * Performances
         */
        #[ORM\OneToMany(
            targetEntity: Performance::class,
            mappedBy: 'artist'
        )]
        #[GraphQL\Association(
            group: 'public',
            description: 'All performances by this artist',
            limit: 50,
        )]
        #[GraphQL\Association(
            group: 'admin',
            alias: 'shows',
            limit: 500,
            criteriaEventName: 'artist.performances.admin',
        )]
        private Collection $performances;

        /**
         * Computed field: Full name
         */
        #[GraphQL\ComputedField(
            type: 'string',
            group: 'public',
            description: 'Full name of the artist',
        )]
        #[GraphQL\ComputedField(
            type: 'string',
            group: 'admin',
            name: 'displayName',
            description: 'Full display name with ID',
        )]
        public function getFullName(): string
        {
            return $this->firstName . ' ' . $this->lastName;
        }

        /**
         * Computed field: Performance count (admin only)
         */
        #[GraphQL\ComputedField(
            type: 'int',
            group: 'admin',
            description: 'Total number of performances',
        )]
        public function getPerformanceCount(): int
        {
            return $this->performances->count();
        }

        // Getters and setters...
    }

Attribute Patterns
==================

Pattern 1: Simple Public API
-----------------------------

.. code-block:: php

    #[GraphQL\Entity]
    class Artist
    {
        #[GraphQL\Field]
        private int $id;

        #[GraphQL\Field]
        private string $name;

        #[GraphQL\Association]
        private Collection $performances;
    }

Pattern 2: Multiple Groups
---------------------------

.. code-block:: php

    #[GraphQL\Entity(group: 'public')]
    #[GraphQL\Entity(group: 'admin')]
    class Artist
    {
        // Public and admin
        #[GraphQL\Field(group: 'public')]
        #[GraphQL\Field(group: 'admin')]
        private int $id;

        // Admin only
        #[GraphQL\Field(group: 'admin')]
        private string $email;
    }

Pattern 3: Performance Optimized
---------------------------------

.. code-block:: php

    #[GraphQL\Entity(
        limit: 100,
        excludeFilters: [Filters::CONTAINS, Filters::STARTSWITH, Filters::ENDSWITH]
    )]
    class Artist
    {
        #[GraphQL\Field(excludeFilters: [Filters::SORT])]
        private string $biography;  // Large text, no sorting

        #[GraphQL\Association(limit: 20)]
        private Collection $performances;
    }

Pattern 4: Custom Event Handling
---------------------------------

.. code-block:: php

    class Artist
    {
        #[GraphQL\Association(
            criteriaEventName: 'artist.activePerformances'
        )]
        private Collection $performances;
    }

    // Event listener
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.activePerformances',
        function (QueryBuilderEvent $event) {
            $event->getQueryBuilder()
                ->andWhere('entity.status = :status')
                ->setParameter('status', 'active');
        }
    );

Pattern 5: Field Aliasing
--------------------------

.. code-block:: php

    class Artist
    {
        #[GraphQL\Field(alias: 'legacyId')]
        private int $oldId;

        #[GraphQL\Field(alias: 'displayName')]
        private string $name;

        #[GraphQL\Association(alias: 'concerts')]
        private Collection $performances;
    }

Best Practices
==============

1. **Use Descriptive Groups**

   .. code-block:: php

       group: 'public'      // Good
       group: 'api_v2'      // Good
       group: 'default'     // Less clear

2. **Document with Descriptions**

   Always provide descriptions for better GraphQL documentation:

   .. code-block:: php

       #[GraphQL\Entity(description: 'Musical artists and bands')]
       #[GraphQL\Field(description: 'Artist unique identifier')]

3. **Set Reasonable Limits**

   Prevent abuse with appropriate limits:

   .. code-block:: php

       #[GraphQL\Entity(limit: 100)]           // Entity level
       #[GraphQL\Association(limit: 50)]       // Association level

4. **Exclude Expensive Filters**

   Improve performance by excluding costly operations:

   .. code-block:: php

       excludeFilters: [
           Filters::CONTAINS,    // LIKE %value%
           Filters::STARTSWITH,  // LIKE value%
           Filters::ENDSWITH,    // LIKE %value
       ]

5. **Use Events for Complex Logic**

   Don't overload attributes - use events for complex scenarios:

   .. code-block:: php

       #[GraphQL\Association(criteriaEventName: 'custom.event')]

6. **Consistent Naming**

   Use consistent event naming:

   .. code-block:: php

       criteriaEventName: 'artist.performances.criteria'
       criteriaEventName: 'artist.recordings.criteria'
       // Pattern: entity.association.type

Common Pitfalls
===============

1. **Using Both Include and Exclude Filters**

   .. code-block:: php

       // ERROR: Mutually exclusive
       #[GraphQL\Field(
           includeFilters: [Filters::EQ],
           excludeFilters: [Filters::NEQ]
       )]

2. **Forgetting Group on Repeated Attributes**

   .. code-block:: php

       // BAD: Both attributes have same group
       #[GraphQL\Field]
       #[GraphQL\Field(description: 'Different description')]

       // GOOD: Different groups
       #[GraphQL\Field(group: 'public')]
       #[GraphQL\Field(group: 'admin')]

3. **Setting Limit to 0**

   .. code-block:: php

       // BAD: 0 means "use default", not "no limit"
       #[GraphQL\Entity(limit: 0)]

       // GOOD: High limit if needed
       #[GraphQL\Entity(limit: 100000)]

4. **Not Registering Custom Types/Strategies**

   .. code-block:: php

       #[GraphQL\Field(type: 'customType')]  // Must register in TypeContainer
       #[GraphQL\Field(hydratorStrategy: CustomStrategy::class)]  // Must register

5. **Alias Conflicts**

   .. code-block:: php

       // BAD: Aliases conflict
       #[GraphQL\Field(alias: 'name')]
       private string $artistName;

       #[GraphQL\Field(alias: 'name')]
       private string $bandName;

Debugging Attributes
====================

View Extracted Metadata
------------------------

.. code-block:: php

    $driver = new Driver($entityManager);
    $metadata = $driver->get(Metadata::class);

    // View all metadata
    print_r($metadata[Artist::class]);

    // View specific field
    print_r($metadata[Artist::class]['fields']['name']);

Check Effective Filters
------------------------

.. code-block:: php

    $filterType = $driver->filter(Artist::class);

    // Introspect available filters
    $fields = $filterType->getFields();
    foreach ($fields as $fieldName => $field) {
        echo "$fieldName filters: " . print_r($field->getType(), true) . "\n";
    }

Test Event Firing
-----------------

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'test.event',
        function($event) {
            echo "Event fired!\n";
            var_dump($event);
        }
    );

Summary
=======

The attribute system provides flexible, declarative configuration for GraphQL schema generation:

- Use ``#[Entity]`` to expose entities
- Use ``#[Field]`` for scalar properties
- Use ``#[Association]`` for relationships
- Use ``#[ComputedField]`` for derived values from entity methods
- Leverage groups for multiple configurations
- Set limits to prevent abuse
- Exclude filters for better performance
- Use events for complex logic
- Provide descriptions for better documentation

**Attribute Targets**:

- ``#[Entity]`` - TARGET_CLASS
- ``#[Field]`` - TARGET_PROPERTY
- ``#[Association]`` - TARGET_PROPERTY
- ``#[ComputedField]`` - TARGET_METHOD

All attributes are repeatable to support multiple groups.

For advanced customization beyond attributes, see :doc:`advanced-topics` and :doc:`events-reference`.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
