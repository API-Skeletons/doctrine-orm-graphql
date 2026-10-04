# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a GraphQL Type Driver for Doctrine ORM that integrates with webonyx/graphql-php. It's a framework-agnostic library that creates GraphQL types from Doctrine ORM entities using PHP attributes.

**Key Point**: This library does NOT redefine how webonyx/graphql-php works. It creates types to be used within that framework's existing patterns.

## Development Commands

### Running Tests
```bash
# Run all tests and quality checks (parallel-lint, phpcs, psalm, phpstan, phpunit)
composer test

# Run only PHPUnit tests
vendor/bin/phpunit

# Run a single test file
vendor/bin/phpunit test/Feature/Type/EntityTest.php

# Run a specific test method
vendor/bin/phpunit --filter testMethodName

# Code coverage (requires Xdebug): a text summary, or an HTML report in coverage-report/
composer coverage-cli
composer coverage-html
```

### Code Coverage

The project keeps 100% line, method and class coverage. After changing `src/`, check it before reporting the work as done, and list any uncovered statements:

```bash
XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --only-summary-for-coverage-text --coverage-clover "$TMPDIR/clover.xml"
```

An uncovered line in the clover report is a `<line type="stmt" count="0">`. For each one, write a test if the branch is reachable, or remove the code if it is not, such as a check only static analysis needs (use a `@var` annotation instead). A race, such as a row deleted by another request between two queries, can be tested with `QueryCountingTestCase::beforeExecute()`, which runs a function just before each statement executes. Do not add `@codeCoverageIgnore` to new code to reach 100%.

### Code Quality
```bash
# Run PHP CodeSniffer (Doctrine Coding Standard)
vendor/bin/phpcs

# Run Psalm (errorLevel 1, set in psalm.xml)
vendor/bin/psalm

# Run PHPStan (level 8, as run by composer test)
vendor/bin/phpstan analyze src --level=8

# Run PHP Parallel Lint
vendor/bin/parallel-lint ./src/ ./test
```

## Architecture

### Core Driver Pattern

The `Driver` class (src/Driver.php) is the main entry point. It extends `Container` and uses the `Services` trait to provide:

- `type(string $id)` - Get a GraphQL type (entity or custom type)
- `connection(string $id)` - Wrap an entity type in a Connection type
- `filter(string $id)` - Get filter InputObjectType for an entity
- `pagination()` - Get the pagination arguments (`first`, `after`, `last`, `before`) to add to the top level of a connection's args
- `resolve(string $id)` - Get resolve closure for an entity
- `input(string $entityClass, array $requiredFields, array $optionalFields, ?string $name)` - Create InputObjectType for mutations; the same entity and fields return the same type, with a stable name
- `completeConnection(string $id)` - Returns a complete GraphQL endpoint definition with type, args, and resolve

### Container System

The library uses a custom PSR-11 compliant Container (src/Container.php) that:
- Stores services and types in a case-insensitive registry
- Supports lazy initialization via closures
- Allows for buildable types (types that depend on other types)
- Enables registration of custom types

Key containers:
- `Driver` - Main container for the entire library
- `EntityTypeContainer` - Manages entity GraphQL types (uses lazy ghost objects)
- `TypeContainer` - Manages custom GraphQL types (DateTime, Blob, etc.)
- `HydratorContainer` - Manages Doctrine Laminas Hydrators for entities

### Metadata System

Metadata is extracted from entity attributes and stored in a `Metadata` object (ArrayObject wrapper):
- `MetadataFactory` builds metadata from PHP attributes on entities. A `Field`, `Association` or `ComputedField` attribute of the configured group in a place it does not apply (a Field on an association, an embeddable or an unmapped property, an Association on a non-association, a ComputedField on a non-public or static method) throws `Exception\Metadata`. After the `metadata.build` event, an exposed association to, or a computed field of the type of, an entity not exposed in the group throws too
- The `Metadata` ArrayObject holds the array form, keyed by entity class; it is what the `metadata.build` event modifies and what is cached. `Metadata::toArray()` exports it with `'__version' => Metadata::FORMAT_VERSION` (a format version, bumped only when the array's shape changes) and the Driver requires that version when given cached metadata
- Code reads typed value objects built from the array: `Metadata\EntityMetadata` with `FieldMetadata`, `AssociationMetadata` and `ComputedFieldMetadata`, via `Entity::getEntityMetadata()`. Their `fromArray()` validates every key and `toArray()` reproduces the array exactly. `Entity::getMetadata()` still returns the array

Attributes are in `src/Attribute/`:
- `#[Entity]` - Marks an entity for GraphQL exposure
- `#[Field]` - Exposes a field
- `#[Association]` - Exposes an association (relationship)
- `#[ComputedField]` - Exposes derived values from entity methods (placed on public methods); its `type` may be an entity class exposed in the group, and `list: true` makes it a list. An entity type's field is a thunk, as an association's is, so entities may refer to each other. Each method parameter is an argument (int/float/string/bool inferred, others typed by the attribute's `args`); values of fields with arguments are cached per set of arguments in `FieldResolver`, and `extract()` leaves those fields out. An `expression` (DQL of the value) makes it filterable and sortable: every alias in it is a `{placeholder}` (`{entity}` is the root, others are renamed per use by `Filter\ComputedFieldExpression`), and `{:name}` is an argument, taken from the filter's `args`. `MetadataFactory` parses each expression with `Query::getAST()` (no database) after the `metadata.build` event, and adds to its `excludeFilters` the filters DQL rejects for it (a subquery takes no IN, IS NULL or LIKE); `between` is applied as two comparisons. A sort selects the expression `AS HIDDEN` and orders by it; the batch id-pair query re-adds hidden selects after replacing the select
- `#[ExcludeFilters]` - Excludes specific filters

### Event System (PSR-14)

Uses league/event (v3.0) for PSR-14 event dispatching. Key events in `src/Event/`:

- `EntityDefinition` - Fired when an entity GraphQL type is created (allows modification of type definition)
- `QueryBuilder` - Fired when QueryBuilder is created for entity and collection resolution (allows custom query modifications)
  - It applies only to the connection or collection that dispatches it; to-one associations and collections without an `eventName` reach the same rows. The docs recommend Doctrine SQL filters for row level security, which apply to every query the driver runs (`test/Feature/Security/SqlFilterTest.php`)
- `Metadata` - Fired when metadata is built

Events can have custom event names via `$eventName` parameter in Driver methods.

### Exceptions

All library exceptions extend `Exception\GraphQL` (a webonyx `Error`), which is not client safe: a client sees only "Internal server error" for a developer's error, whose message may name classes and methods. `Exception\ClientError` (`Filter`, `Pagination`, `TypeSerialization`) is client safe unless it has a previous exception which is not; throw one only for an error a client's request causes, with a message naming only GraphQL names and the client's values. See `docs/errors.rst`.

### Filter System

Filters are auto-generated for all exposed fields and associations (src/Filter/):

- `FilterFactory` creates filter InputObjectTypes
- `Filters` enum defines available filter types (eq, neq, lt, lte, gt, gte, between, contains, startswith, endswith, in, notin, isnull, sort, sortPriority); `sort` takes the `SortDirection` enum (ASC, DESC)
- Date and time filter values are bound as the field's Doctrine type
- Filters are context-aware based on field type
- Can be excluded globally via Config or per-entity/field via attributes

### Resolution and Hydration

- `ResolveEntityFactory` creates resolve closures for entity queries
- `ResolveCollectionFactory` creates resolve closures for associations
- `FieldResolver` resolves individual fields
- A page of a query with a join a QueryBuilder listener added, fetched or not, is fetched by Doctrine's `Paginator`, which limits entities rather than rows (`Trait\FetchPage`); a collection's own many-to-many `source` join needs none
- A row of a subclass which is not exposed is resolved as its nearest exposed parent class (`EntityTypeContainer::getExposedClass()`), in `FieldResolver` and `ResolveCollectionFactory`
- Connections are ordered by the root entity's identifier after any other ordering (`Trait\OrderByIdentifier`), added after the QueryBuilder event so a listener's ordering comes first. A collection is ordered by its association's `#[ORM\OrderBy]` just before the identifier (`ResolveCollectionFactory::orderByAssociation()`)
- Batching (`batchAssociations`, on by default) returns `GraphQL\Deferred`:
  - `ToOneLoader` loads unloaded to-one proxies with one `IN` query per class
  - `ResolveCollectionFactory` groups sources resolving the same association with the same arguments into a `CollectionBatch`: one query fetches the source/target id pairs of every row, capped at `batchLimit` (pairs, because Doctrine returns an entity once however many rows it is in); they give each source's count and page, and one query loads only the targets on a page. Over `batchLimit`, each source's page is queried, with one GROUP BY count query only when a page needs it
  - Not batched: associations with an `eventName` (their QueryBuilder event is per source) and composite identifiers or an association as the identifier (derived identity)
  - `CollectionBatchTest` checks batched results against per-source results for every form of pagination
- Identifiers are matched and bound as their database values (`Trait\DatabaseValue`), as a custom type such as `uuid_binary` stores them in another form: Doctrine converts neither an identifier in an IN list or bound as an entity, nor a scalar query result. Filter values of custom (non-DBAL) types are converted by the type, as `find()` converts an identifier; `TypedIdentifierTest` uses `test/DbalType/CodeType`
- Uses Doctrine Laminas Hydrator for extracting entity data to arrays
- Supports extraction strategies (FieldDefault, AssociationDefault, ToBoolean, ToFloat, ToInteger, ToString)
- Strategies implement `Hydrator\Strategy\Strategy` interface, which extends the Laminas interface; `extract()` receives the Doctrine field name (not the GraphQL alias) as a third argument. Strategies are shared instances in `HydratorContainer`, except that each collection association gets its own clone of a `CollectionStrategyInterface` strategy, as the Doctrine hydrator sets the collection name and class metadata on it. Laminas-only strategies are still called with two arguments.
- `AssociationDefault` must implement Doctrine's `CollectionStrategyInterface`: the Doctrine hydrator requires it for collection-valued associations. Do not extend Doctrine's `AbstractCollectionStrategy`, which is `@internal`

### Config Options

`Config` class (src/Config.php) supports:
- `group` - Allows multiple GraphQL schemas from same entities
- `groupSuffix` - Custom suffix for type names
- `batchAssociations` - Load associations in batches (default true)
- `batchLimit` - Most row id pairs a batched collection field fetches with one query (default 1000)
- `useHydratorCache` - Cache hydrator results for as long as the entity exists
- `useQueryResultCache` - Cache query results for identical SQL and parameters until the entity manager is cleared or flushed (`Cache\QueryResultCacheListener`)
- `limit` - Default limit on the rows of a connection (default: 1000); an entity's `limit`, then an association's, replaces it, even when larger
- `extractByValue` - Extract by value vs reference for every entity, overriding the `extractByValue` argument of the `#[Entity]` attribute
- `entityPrefix` - Remove prefix from type names
- `sortFields` - Sort fields alphabetically
- `excludeFilters` - Globally exclude specific filters
- `useNonNullTypes` - Make an entity type's identifier, the fields of columns which are not nullable, and owning to-one associations whose join columns are not nullable non-null types (default false)
- `formatJsonAs` - `Type\JsonFormat::String` (default, a JSON document string) or `JsonFormat::Object` (the value itself) for the `Json` scalar

## Testing Approach

Tests use in-memory SQLite database with test entities in `test/Entity/`:
- `Artist`, `Performance`, `Recording`, `User` - Relational test data
- `TypeTest` - Tests all Doctrine data types

Base test class `TestCase` (test/TestCase.php):
- Sets up EntityManager with attribute metadata
- Populates test data (Grateful Dead, Phish, etc.)
- Uses `setUp()` for per-test isolation

Test organization:
- Feature tests in `test/Feature/` organized by functionality
- Tests verify GraphQL schema generation, query resolution, filtering, pagination, events, etc.

## Key Architectural Patterns

1. **Lazy Initialization**: Entity types use PHP 8.4 Lazy Ghost objects to defer construction
2. **Type Registry**: All types registered in containers by lowercase ID
3. **Buildable Types**: Types that depend on other types (e.g., Connection wraps Entity type)
4. **Event-Driven Customization**: Events allow modification at key points (type definition, query building)
5. **Attribute-Based Configuration**: PHP 8 attributes configure GraphQL exposure
6. **Complete Connection Model**: Follows GraphQL pagination spec with edges/nodes/pageInfo

## Important Notes

- PHP 8.4+ required
- Doctrine ORM 2.20.9+ or 3.0+ required (`^2.20.9 || ^3.0`); on PHP 8.5 the lowest installable ORM is 3.3.1. CI tests ORM 2.20.9, 3.0.0 and ^3.0
- Default branch is `14.0.x`, the next release (upstream is `API-Skeletons/doctrine-orm-graphql`; `origin` is a fork)
- Record breaking changes in the "13.x to 14.0" section of `docs/upgrade.rst`
- This library is framework-agnostic (can be used with Laravel, Symfony, etc.)
- Type names are suffixed with group name by default (can be customized via groupSuffix config)
