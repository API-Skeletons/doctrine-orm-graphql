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

# Generate code coverage report (requires Xdebug)
composer coverage
```

### Code Quality
```bash
# Run PHP CodeSniffer (Doctrine Coding Standard)
vendor/bin/phpcs

# Run Psalm (level 4)
vendor/bin/psalm

# Run PHPStan (level 5)
vendor/bin/phpstan analyze src --level=5

# Run PHP Parallel Lint
vendor/bin/parallel-lint ./src/ ./test
```

## Architecture

### Core Driver Pattern

The `Driver` class (src/Driver.php) is the main entry point. It extends `Container` and uses the `Services` trait to provide:

- `type(string $id)` - Get a GraphQL type (entity or custom type)
- `connection(string $id)` - Wrap an entity type in a Connection type
- `filter(string $id)` - Get filter InputObjectType for an entity
- `pagination()` - Get pagination type
- `resolve(string $id)` - Get resolve closure for an entity
- `input(string $entityClass, array $requiredFields, array $optionalFields)` - Create InputObjectType for mutations
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
- `MetadataFactory` builds metadata from PHP attributes on entities
- `GlobalEnable` feature enables all fields/associations without attributes (useful for development)
- Metadata is cached per entity and includes field mappings, association mappings, limits, filters, etc.

Attributes are in `src/Attribute/`:
- `#[Entity]` - Marks an entity for GraphQL exposure
- `#[Field]` - Exposes a field
- `#[Association]` - Exposes an association (relationship)
- `#[ExcludeFilters]` - Excludes specific filters

### Event System (PSR-14)

Uses league/event (v3.0) for PSR-14 event dispatching. Key events in `src/Event/`:

- `EntityDefinition` - Fired when an entity GraphQL type is created (allows modification of type definition)
- `QueryBuilder` - Fired when QueryBuilder is created for entity resolution (allows custom query modifications)
- `Criteria` - Fired for association filtering
- `Metadata` - Fired when metadata is built

Events can have custom event names via `$eventName` parameter in Driver methods.

### Filter System

Filters are auto-generated for all exposed fields and associations (src/Filter/):

- `FilterFactory` creates filter InputObjectTypes
- `Filters` enum defines available filter types (eq, neq, lt, lte, gt, gte, isnull, between, in, notin, startwith, endswith, contains, sort, sortPriority)
- Filters are context-aware based on field type
- Can be excluded globally via Config or per-entity/field via attributes

### Resolution and Hydration

- `ResolveEntityFactory` creates resolve closures for entity queries
- `ResolveCollectionFactory` creates resolve closures for associations
- `FieldResolver` resolves individual fields
- Uses Doctrine Laminas Hydrator for extracting entity data to arrays
- Supports extraction strategies (e.g., ToBoolean, ToFloat, ToInteger, Collection handling)

### Config Options

`Config` class (src/Config.php) supports:
- `group` - Allows multiple GraphQL schemas from same entities
- `groupSuffix` - Custom suffix for type names
- `useHydratorCache` - Cache hydrator results per request
- `limit` - Hard limit for collections (default: 1000)
- `globalEnable` - Enable all fields/associations without attributes
- `ignoreFields` - Fields to ignore with globalEnable
- `globalByValue` - Extract by value vs reference
- `entityPrefix` - Remove prefix from type names
- `sortFields` - Sort fields alphabetically
- `excludeFilters` - Globally exclude specific filters

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
- Doctrine ORM 3.6+ required
- Main branch is `12.5.x`
- This library is framework-agnostic (can be used with Laravel, Symfony, etc.)
- Type names are suffixed with group name by default (can be customized via groupSuffix config)
