==================
Config Reference
==================

The ``Config`` class provides configuration options for the Driver. All configuration parameters are set at construction and are immutable (readonly properties).

Class Overview
==============

.. code-block:: php

    namespace ApiSkeletons\Doctrine\ORM\GraphQL;

    class Config
    {
        public function __construct(array $config = []);

        public function getGroup(): string;
        public function getGroupSuffix(): ?string;
        public function getUseHydratorCache(): bool;
        public function getUseQueryResultCache(): bool;
        public function getLimit(): int;
        public function getGlobalEnable(): bool;
        public function getIgnoreFields(): array;
        public function getGlobalByValue(): ?bool;
        public function getEntityPrefix(): ?string;
        public function getSortFields(): ?bool;
        public function getExcludeFilters(): array;
    }

Constructor
===========

.. code-block:: php

    public function __construct(array $config = [])

Creates a new Config instance with specified options. All options are optional and have sensible defaults.

Parameters
----------

**$config** : ``array``
    Associative array of configuration options. Unknown keys throw ``ConfigurationException``.

Available Options
-----------------

.. code-block:: php

    $config = new Config([
        'group' => 'default',            // string
        'groupSuffix' => null,           // string|null
        'useHydratorCache' => false,     // bool
        'useQueryResultCache' => false,  // bool
        'limit' => 1000,                 // int
        'globalEnable' => false,         // bool
        'ignoreFields' => [],            // string[]
        'globalByValue' => null,         // bool|null
        'entityPrefix' => null,          // string|null
        'sortFields' => null,            // bool|null
        'excludeFilters' => [],          // Filters[]
    ]);

Throws
------

``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration``
    When an invalid configuration key is provided. Exception includes valid options.

Configuration Options
=====================

group
-----

**Type**: ``string``

**Default**: ``'default'``

**Description**:

The attribute group to load. Entities and fields must have matching ``group`` attributes
to be included in the schema. This allows multiple GraphQL configurations from the same
entities.

**Use Cases**:

- Separate public vs admin APIs
- Different field exposure for different consumers
- Multiple API versions from same entities

**Examples**:

.. code-block:: php

    // Entity with multiple groups
    #[GraphQL\Entity(group: 'public')]
    #[GraphQL\Entity(group: 'admin')]
    class Artist
    {
        #[GraphQL\Field(group: 'public')]
        #[GraphQL\Field(group: 'admin')]
        private int $id;

        #[GraphQL\Field(group: 'public')]
        #[GraphQL\Field(group: 'admin')]
        private string $name;

        #[GraphQL\Field(group: 'admin')]  // Admin only
        private string $email;
    }

    // Public driver - no email field
    $publicDriver = new Driver($em, new Config(['group' => 'public']));

    // Admin driver - includes email field
    $adminDriver = new Driver($em, new Config(['group' => 'admin']));

**Naming Convention**:

Type names are suffixed with the group name: ``Artist_public``, ``Artist_admin``

groupSuffix
-----------

**Type**: ``string|null``

**Default**: ``null`` (uses group name as suffix)

**Description**:

Customizes the suffix appended to type names. Set to empty string ``''`` to remove suffix entirely.

**Examples**:

.. code-block:: php

    // Default behavior: group name as suffix
    new Config(['group' => 'public']);
    // Type name: Artist_public

    // Custom suffix
    new Config(['group' => 'public', 'groupSuffix' => '_api']);
    // Type name: Artist_api

    // No suffix
    new Config(['group' => 'public', 'groupSuffix' => '']);
    // Type name: Artist

**Combined with entityPrefix**:

.. code-block:: php

    new Config([
        'group' => 'public',
        'groupSuffix' => '',
        'entityPrefix' => 'App\\Entity\\',
    ]);
    // Type name: Artist (clean!)
    // Without these: App_Entity_Artist_public

**Warning**:

Using the same ``groupSuffix`` for different groups can cause type name collisions.
Ensure uniqueness across all Driver instances in your application.

useHydratorCache
----------------

**Type**: ``bool``

**Default**: ``false``

**Description**:

Enables request-scoped caching of hydration results. When enabled, each entity is
extracted once per request, and subsequent accesses return cached data.

**Performance Impact**:

- **Memory**: Increases memory usage (stores extracted arrays)
- **Speed**: Faster for entities accessed multiple times in a single query
- **Use Case**: Deep GraphQL queries with repeated entity references

**When to Enable**:

- Queries with deeply nested associations
- Queries that reference the same entity multiple times
- Circular references in the graph

**When to Disable**:

- Simple, shallow queries
- Memory-constrained environments
- Single-access patterns

**Examples**:

.. code-block:: php

    // Without cache: Artist extracted 3 times
    {
        artist(filter: { id: { eq: "1" } }) {
            edges {
                node {
                    name  # Extract artist
                    performances {
                        edges {
                            node {
                                artist { name }  # Extract artist again
                            }
                        }
                    }
                }
            }
        }
    }

    // With cache: Artist extracted once, cached for subsequent accesses
    new Config(['useHydratorCache' => true]);

**Scope**:

Cache is request-scoped only. Cleared after each GraphQL query execution.

useQueryResultCache
-------------------

**Type**: ``bool``

**Default**: ``false``

**Description**:

Enables request-scoped caching of database query results. When enabled, identical
SQL queries with the same parameters are executed only once per request, with
subsequent executions returning cached results.

**Use Cases**:

- Complex nested queries that may execute the same query multiple times
- Circular references in the GraphQL query
- Queries accessing the same associations repeatedly
- Performance optimization for duplicate data access patterns

**How It Works**:

The cache generates a signature from the SQL query string and parameters. When a
query is executed, the cache is checked first. If found, cached results are returned
without database access.

**Performance Impact**:

.. code-block:: graphql

    # Without cache: artist performances queried twice
    {
        artist1: artist(id: 1) {
            performances { edges { node { venue } } }
        }
        artist2: artist(id: 1) {  # Same artist
            performances { edges { node { venue } } }  # Duplicate query
        }
    }

    # With cache: second performances query uses cached results
    new Config(['useQueryResultCache' => true]);

**Cache Statistics**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;

    $cache = $driver->get(QueryResultCache::class);
    $stats = $cache->getStats();

    echo "Cache size: " . $stats['size'] . "\n";
    echo "Cache hits: " . $stats['hits'] . "\n";
    echo "Cache misses: " . $stats['misses'] . "\n";
    echo "Hit rate: " . ($stats['hitRate'] * 100) . "%\n";

**Scope**:

Cache is request-scoped only. Automatically cleared after each request.

**Difference from useHydratorCache**:

- ``useQueryResultCache``: Caches database query results (SQL level)
- ``useHydratorCache``: Caches entity extraction results (hydration level)

Both can be enabled simultaneously for maximum caching.

limit
-----

**Type**: ``int``

**Default**: ``1000``

**Description**:

Hard limit for maximum results per query. Prevents abuse by limiting the number of
entities returned, regardless of pagination parameters.

**Behavior**:

- Applies to all entity queries and associations
- Overridden by entity-level limits (``#[Entity(limit: 500)]``)
- Overridden by association-level limits (``#[Association(limit: 100)]``)
- Acts as a ceiling - pagination can request less, never more

**Priority Order** (highest to lowest):

1. Association-level limit: ``#[Association(limit: 10)]``
2. Entity-level limit: ``#[Entity(limit: 50)]``
3. Config limit: ``new Config(['limit' => 1000])``

**Examples**:

.. code-block:: php

    // Conservative limit for public API
    $publicConfig = new Config(['limit' => 100]);

    // Higher limit for admin API
    $adminConfig = new Config(['limit' => 5000]);

    // Development: no practical limit
    $devConfig = new Config(['limit' => 100000]);

**GraphQL Behavior**:

.. code-block:: graphql

    # Request 5000 items with limit: 1000
    {
        artists(pagination: { first: 5000 }) {
            edges { node { name } }
        }
    }
    # Returns maximum 1000 items

**Security Consideration**:

Always set a reasonable limit to prevent denial-of-service attacks via expensive queries.

globalEnable
------------

**Type**: ``bool``

**Default**: ``false``

**Description**:

When ``true``, exposes ALL fields and associations for ALL entities without requiring
attributes. Useful for development when schema changes frequently.

**Use Cases**:

- Rapid prototyping
- Development environments
- Dynamic schema exploration
- GraphQL API generation tools

**NOT Recommended For**:

- Production environments (security risk)
- Stable APIs (explicit is better than implicit)
- APIs with authentication/authorization

**Behavior**:

- All Doctrine entities become GraphQL types
- All entity fields become GraphQL fields
- All associations become GraphQL associations
- Respects ``ignoreFields`` configuration
- Attributes still apply (override defaults)

**Examples**:

.. code-block:: php

    // Development: expose everything
    $devDriver = new Driver($em, new Config([
        'globalEnable' => true,
        'ignoreFields' => ['password', 'apiKey', 'secret'],
    ]));

    // Production: explicit attributes required
    $prodDriver = new Driver($em, new Config([
        'globalEnable' => false,
    ]));

**Entity Without Attributes**:

.. code-block:: php

    // No attributes required with globalEnable
    class Artist
    {
        private int $id;        // Exposed
        private string $name;   // Exposed
        private string $password;  // Hidden (in ignoreFields)
    }

**Mixed with Attributes**:

.. code-block:: php

    // Attributes override globalEnable defaults
    class Artist
    {
        private int $id;  // Exposed with defaults

        #[GraphQL\Field(description: 'Custom description')]
        private string $name;  // Exposed with custom description

        #[GraphQL\Field(excludeFilters: [Filters::CONTAINS])]
        private string $email;  // Exposed without CONTAINS filter
    }

ignoreFields
------------

**Type**: ``array`` of ``string``

**Default**: ``[]``

**Description**:

Array of field/association names to exclude when ``globalEnable`` is ``true``.
Has no effect when ``globalEnable`` is ``false``.

**Use Cases**:

- Exclude sensitive fields (passwords, API keys, secrets)
- Exclude internal fields (timestamps, internal IDs)
- Exclude heavy fields (large blobs, computed fields)

**Examples**:

.. code-block:: php

    new Config([
        'globalEnable' => true,
        'ignoreFields' => [
            'password',
            'passwordHash',
            'apiKey',
            'apiSecret',
            'privateKey',
            'internalId',
            'rawData',
        ],
    ]);

**Applies Globally**:

The same field names are ignored across ALL entities.

.. code-block:: php

    // Ignored in all entities
    ignoreFields' => ['password']

    class User
    {
        private string $password;  // Hidden
    }

    class Admin
    {
        private string $password;  // Hidden
    }

globalByValue
-------------

**Type**: ``bool|null``

**Default**: ``null``

**Description**:

Controls how Doctrine Laminas Hydrator extracts entity data:

- ``true``: Extract by value (deep copy, no lazy loading)
- ``false``: Extract by reference (preserves lazy loading)
- ``null``: Use entity-level ``byValue`` attribute or default (true)

**Extract by Value** (``true``):

- Creates deep copies of entity data
- Breaks lazy loading (all associations loaded immediately)
- Safe for serialization
- Higher memory usage

**Extract by Reference** (``false``):

- Maintains references to original entities
- Preserves lazy loading
- Lower memory usage
- Can cause side effects if entities modified

**When to Use**:

**By Value** (true):
    - Default for most use cases
    - When lazy loading causes issues
    - When data will be serialized
    - When GraphQL query is simple/shallow

**By Reference** (false):
    - Deep queries with many associations
    - When lazy loading is beneficial
    - When memory is constrained
    - Advanced use cases only

**Examples**:

.. code-block:: php

    // Force all entities to extract by value
    new Config(['globalByValue' => true]);

    // Force all entities to extract by reference
    new Config(['globalByValue' => false]);

    // Use entity-level settings (default)
    new Config(['globalByValue' => null]);

**Per-Entity Override**:

.. code-block:: php

    #[GraphQL\Entity(byValue: false)]  // Extract by reference
    class Artist { /* ... */ }

    // globalByValue overrides entity attribute
    new Config(['globalByValue' => true]);  // Forces by value

**Performance Consideration**:

Extracting by reference is generally faster but can have unexpected behavior. Use
by value unless you have specific performance needs and understand the tradeoffs.

entityPrefix
------------

**Type**: ``string|null``

**Default**: ``null``

**Description**:

Namespace prefix to strip from type names. Simplifies type names by removing
common entity namespace.

**Examples**:

.. code-block:: php

    // Without entityPrefix
    // Type name: App_Entity_Artist_default

    // With entityPrefix
    new Config(['entityPrefix' => 'App\\Entity\\']);
    // Type name: Artist_default

    // Combined with groupSuffix
    new Config([
        'entityPrefix' => 'App\\Entity\\',
        'groupSuffix' => '',
    ]);
    // Type name: Artist (clean!)

**Namespace Handling**:

The prefix is removed from the beginning of the class name before converting to
a GraphQL type name.

.. code-block:: php

    // Entity: App\Entity\Domain\Artist
    // Prefix: 'App\\Entity\\'
    // Result: Domain_Artist_default

**Warning**:

Ensure all entities share the same prefix. Mixing prefixes can cause unexpected
type names.

sortFields
----------

**Type**: ``bool|null``

**Default**: ``null`` (unsorted)

**Description**:

When ``true``, sorts entity fields alphabetically in the GraphQL schema.
Improves readability of generated documentation.

**Behavior**:

- Sorts after all metadata extraction and events
- Affects field order in GraphQL introspection
- Does not affect query execution or performance

**Use Cases**:

- Better documentation readability
- Consistent field ordering across schema versions
- Easier diffing of schema changes

**Examples**:

.. code-block:: php

    // Alphabetically sorted fields
    new Config(['sortFields' => true]);

    // Original order (order of attributes/class properties)
    new Config(['sortFields' => false]);

**GraphQL Introspection**:

.. code-block:: graphql

    # sortFields: false
    type Artist {
        id: Int
        name: String
        description: String
        createdAt: DateTime
        performances: PerformanceConnection
    }

    # sortFields: true
    type Artist {
        createdAt: DateTime
        description: String
        id: Int
        name: String
        performances: PerformanceConnection
    }

excludeFilters
--------------

**Type**: ``array`` of ``Filters``

**Default**: ``[]``

**Description**:

Array of filter types to exclude globally for all entities, fields, and associations.

**Available Filters**:

- ``Filters::EQ`` - Equals
- ``Filters::NEQ`` - Not equals
- ``Filters::LT`` - Less than
- ``Filters::LTE`` - Less than or equal
- ``Filters::GT`` - Greater than
- ``Filters::GTE`` - Greater than or equal
- ``Filters::BETWEEN`` - Between (range)
- ``Filters::IN`` - In array
- ``Filters::NOTIN`` - Not in array
- ``Filters::CONTAINS`` - Contains (like %value%)
- ``Filters::STARTSWITH`` - Starts with (like value%)
- ``Filters::ENDSWITH`` - Ends with (like %value)
- ``Filters::ISNULL`` - Is null / Is not null
- ``Filters::SORT`` - Sort order (ASC/DESC)
- ``Filters::SORTPRIORITY`` - Sort priority for multi-field sorts

**Use Cases**:

- Disable expensive filters (CONTAINS on large text fields)
- Simplify API (remove unused filter types)
- Security (prevent certain query patterns)
- Database-specific (some DBs don't support certain operations)

**Examples**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    // Disable LIKE filters globally (performance)
    new Config([
        'excludeFilters' => [
            Filters::CONTAINS,
            Filters::STARTSWITH,
            Filters::ENDSWITH,
        ],
    ]);

    // Disable range filters (security)
    new Config([
        'excludeFilters' => [
            Filters::LT,
            Filters::GT,
            Filters::LTE,
            Filters::GTE,
            Filters::BETWEEN,
        ],
    ]);

    // Simplify to equality only
    new Config([
        'excludeFilters' => [
            Filters::NEQ,
            Filters::LT,
            Filters::LTE,
            Filters::GT,
            Filters::GTE,
            Filters::BETWEEN,
            Filters::IN,
            Filters::NOTIN,
            Filters::CONTAINS,
            Filters::STARTSWITH,
            Filters::ENDSWITH,
        ],
    ]);

**Priority Order** (highest to lowest):

1. Field-level ``excludeFilters``: ``#[Field(excludeFilters: [...])]``
2. Entity-level ``excludeFilters``: ``#[Entity(excludeFilters: [...])]``
3. Config-level ``excludeFilters``: ``new Config(['excludeFilters' => [...]])``

All three levels are merged (additive).

Configuration Patterns
======================

Development Configuration
--------------------------

.. code-block:: php

    $devConfig = new Config([
        'group' => 'default',
        'globalEnable' => true,
        'ignoreFields' => ['password', 'apiKey'],
        'limit' => 10000,
        'sortFields' => true,
        'useHydratorCache' => false,
    ]);

Production Configuration
------------------------

.. code-block:: php

    $prodConfig = new Config([
        'group' => 'public',
        'globalEnable' => false,  // Explicit attributes only
        'limit' => 100,
        'entityPrefix' => 'App\\Entity\\',
        'groupSuffix' => '',
        'sortFields' => true,
        'useHydratorCache' => true,
        'useQueryResultCache' => true,
        'excludeFilters' => [
            Filters::CONTAINS,  // Expensive on large datasets
        ],
    ]);

Admin API Configuration
-----------------------

.. code-block:: php

    $adminConfig = new Config([
        'group' => 'admin',
        'limit' => 1000,
        'globalEnable' => false,
        'useHydratorCache' => true,
        'sortFields' => true,
    ]);

Multiple API Versions
----------------------

.. code-block:: php

    // API v1: Legacy support
    $v1Config = new Config([
        'group' => 'v1',
        'limit' => 100,
    ]);

    // API v2: Enhanced features
    $v2Config = new Config([
        'group' => 'v2',
        'limit' => 500,
        'sortFields' => true,
    ]);

    // Separate drivers and schemas
    $v1Driver = new Driver($em, $v1Config);
    $v2Driver = new Driver($em, $v2Config);

Validation and Error Handling
==============================

Invalid Configuration Keys
--------------------------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration;

    try {
        new Config(['invalidKey' => 'value']);
    } catch (Configuration $e) {
        echo $e->getMessage();
        // "Invalid configuration setting: invalidKey"

        $validKeys = $e->getValidKeys();
        // ['group', 'groupSuffix', 'useHydratorCache', ...]
    }

ConfigBuilder (Alternative API)
===============================

For fluent configuration building:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;

    $config = (new ConfigBuilder())
        ->setGroup('public')
        ->setLimit(100)
        ->setGlobalEnable(false)
        ->setIgnoreFields(['password'])
        ->setEntityPrefix('App\\Entity\\')
        ->setSortFields(true)
        ->build();

    $driver = new Driver($entityManager, $config);

See ``src/ConfigBuilder.php`` for implementation details.

Best Practices
==============

1. **Use Explicit Configuration**: Don't rely on defaults - be explicit about your intent
2. **Different Configs for Different Environments**: Dev should enable ``globalEnable``, production should not
3. **Set Reasonable Limits**: Prevent abuse with appropriate ``limit`` values
4. **Use Groups for Multiple APIs**: Separate public/admin/internal APIs with different groups
5. **Clean Type Names**: Use ``entityPrefix`` and ``groupSuffix`` for readable type names
6. **Exclude Sensitive Fields**: Always use ``ignoreFields`` with ``globalEnable``
7. **Profile Before Caching**: Only enable ``useHydratorCache`` and ``useQueryResultCache`` if profiling shows benefit
8. **Document Custom Configs**: Comment why you're using non-default values

Common Pitfalls
===============

1. **globalEnable in Production**: Security risk - always use explicit attributes
2. **Same groupSuffix for Different Groups**: Causes type name collisions
3. **Too High Limits**: Can cause memory exhaustion and slow queries
4. **Cache Always On**: ``useHydratorCache`` and ``useQueryResultCache`` waste memory for simple queries
5. **Empty entityPrefix**: Results in ugly type names like ``App_Entity_Artist_default``
6. **Forgetting ignoreFields**: Exposes sensitive data with ``globalEnable``
