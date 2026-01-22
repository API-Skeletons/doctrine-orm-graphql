=====================================================
GraphQL Type Driver for Doctrine ORM - Developer Guide
=====================================================

Introduction
============

This is comprehensive technical documentation for the Doctrine ORM GraphQL library, written for experienced PHP developers. This library provides a framework-agnostic GraphQL type driver that integrates Doctrine ORM with webonyx/graphql-php.

**Core Philosophy**: This library does not redefine how webonyx/graphql-php works. Instead, it generates properly structured types to be used within that framework's existing patterns.

Requirements
------------

- PHP 8.4+
- Doctrine ORM 3.6+
- webonyx/graphql-php ^15.0
- league/event ^3.0 (PSR-14 compliant)

Key Features
============

Type Generation
---------------

- Automatic GraphQL type generation from Doctrine entities
- Support for all Doctrine data types with extensibility for custom types
- Configurable field exposure via PHP attributes
- Multiple configuration groups within the same codebase

Query Resolution
---------------

- GraphQL Complete Connection Model implementation
- Cursor-based pagination
- Database-level filtering using QueryBuilder (not in-memory)
- Nested collection filtering with performance optimization
- Association traversal with lazy loading

Filtering System
----------------

- 15 built-in filter types (eq, neq, lt, lte, gt, gte, between, in, notin, contains, startswith, endswith, isnull, sort, sortPriority)
- Context-aware filters based on field types
- Configurable filter exclusion at global, entity, field, or association level
- Optimized database queries with proper index utilization

Event System
------------

- PSR-14 compliant event dispatching
- Events for entity definition modification
- Events for QueryBuilder customization
- Events for metadata manipulation
- Custom event names for granular control

Performance Optimizations
-------------------------

- QueryBuilder-based collection resolution (83% faster than Criteria-based)
- Shared pagination service (eliminated ~120 lines of duplicate code)
- Database-level filtering (100x+ improvement for large collections)
- Request-scoped hydrator caching
- Lazy ghost object initialization for entity types

Architecture Overview
=====================

Container System
----------------

The library uses a custom PSR-11 compliant container system:

**Driver**
  Main container extending ``Container`` class. Entry point for all functionality.

**EntityTypeContainer**
  Manages Doctrine entity GraphQL types using PHP 8.4 Lazy Ghost objects for deferred initialization.

**TypeContainer**
  Manages custom GraphQL types (DateTime, Date, Blob, JSON, etc.).

**HydratorContainer**
  Manages Doctrine Laminas Hydrators for entity data extraction.

Service Architecture
--------------------

The ``Driver`` class uses the ``Services`` trait to register core services:

- EntityManager (Doctrine)
- Config (library configuration)
- EventDispatcher (PSR-14)
- MetadataFactory (entity metadata extraction)
- ResolveEntityFactory (entity query resolution)
- ResolveCollectionFactory (association query resolution)
- FilterFactory (filter generation)
- PaginationService (cursor-based pagination)

All services are registered with lazy initialization to minimize memory footprint.

Metadata System
---------------

Metadata is extracted from entity attributes at runtime:

1. **Attribute Scanning**: MetadataFactory scans entities for GraphQL attributes
2. **Metadata Storage**: Results stored in ``Metadata`` (ArrayObject wrapper)
3. **Event Dispatch**: Metadata events allow runtime modification
4. **GlobalEnable**: Optional feature to expose all fields without attributes

The metadata structure includes:

- Field mappings (type, nullable, description, filters)
- Association mappings (type, target, filters, limits)
- Entity limits (global, per-association)
- Extraction maps (field aliasing)

Resolution Process
------------------

When a GraphQL query is executed:

1. **Type Resolution**: Driver resolves entity types from EntityTypeContainer
2. **Query Parsing**: webonyx/graphql-php parses the GraphQL query
3. **Field Resolution**: ResolveEntityFactory creates resolve closures
4. **QueryBuilder Construction**: Database queries built with Doctrine QueryBuilder
5. **Filter Application**: Filters applied at database level
6. **Pagination**: PaginationService handles offset/limit calculation
7. **Hydration**: Results extracted to arrays via Doctrine Laminas Hydrator
8. **Response Building**: Connection model response (edges, nodes, pageInfo)

Quick Start
===========

Basic Setup
-----------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
    use Doctrine\ORM\EntityManager;
    use GraphQL\Type\Definition\ObjectType;
    use GraphQL\Type\Schema;

    // Configure entities with attributes
    #[GraphQL\Entity]
    class Artist
    {
        #[GraphQL\Field]
        private int $id;

        #[GraphQL\Field(description: 'Artist name')]
        private string $name;

        #[GraphQL\Association(description: 'Artist performances')]
        private Collection $performances;
    }

    // Create driver
    $driver = new Driver($entityManager);

    // Build schema
    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(Artist::class),
            ],
        ]),
    ]);

    // Execute query
    $result = GraphQL::executeQuery($schema, '{
        artists {
            edges {
                node {
                    id
                    name
                    performances {
                        edges {
                            node {
                                venue
                            }
                        }
                    }
                }
            }
        }
    }');

Configuration Options
---------------------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    $config = new Config([
        'group' => 'default',           // Attribute group to load
        'groupSuffix' => '_default',    // Suffix for type names
        'limit' => 1000,                // Max results per query
        'globalEnable' => false,        // Auto-expose all fields
        'ignoreFields' => ['password'], // Fields to ignore
        'globalByValue' => true,        // Extract by value vs reference
        'entityPrefix' => 'App\\Entity\\', // Remove from type names
        'sortFields' => true,           // Alphabetize fields
        'useHydratorCache' => false,    // Cache hydration results
        'excludeFilters' => [           // Globally exclude filters
            Filters::CONTAINS,
        ],
    ]);

    $driver = new Driver($entityManager, $config);

Documentation Structure
=======================

.. toctree::
   :maxdepth: 2
   :caption: Core Concepts

   architecture
   driver-reference
   config-reference
   attributes-reference

.. toctree::
   :maxdepth: 2
   :caption: Features

   filters-reference
   events-reference
   types-reference
   pagination
   mutations

.. toctree::
   :maxdepth: 2
   :caption: Advanced Topics

   advanced-topics
   performance
   internals
   migration-guide

.. toctree::
   :maxdepth: 1
   :caption: Reference

   api-index
   changelog

Conventions Used
================

Code Examples
-------------

All code examples assume the following imports unless otherwise specified:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
    use Doctrine\ORM\EntityManager;
    use GraphQL\GraphQL;
    use GraphQL\Type\Definition\ObjectType;
    use GraphQL\Type\Schema;

Type Notation
-------------

- ``EntityManager``: Doctrine ORM EntityManager
- ``Driver``: The main library Driver class
- ``Entity``: Internal representation of a Doctrine entity (Type\\Entity\\Entity)
- ``ObjectType``: webonyx/graphql-php ObjectType
- ``Connection``: GraphQL connection wrapper type

Version Information
===================

Current Version: 12.x
----------------------

- Supports league/event 3.0 (PSR-14 compliant)
- PHP 8.4+ required
- Doctrine ORM 3.6+ required

Previous Version: 11.x
----------------------

- Supports league/event 2.2 (non-PSR-14)
- No longer maintained

Upgrade Path
------------

See :doc:`migration-guide` for upgrading from 11.x to 12.x.

License
=======

This library is licensed under the MIT License. See LICENSE file in the repository.

Contributing
============

Contributions are welcome! See the GitHub repository at https://github.com/API-Skeletons/doctrine-orm-graphql

Support
=======

- Documentation: https://doctrine-orm-graphql.apiskeletons.dev
- Issues: https://github.com/API-Skeletons/doctrine-orm-graphql/issues
- Live Example: https://graphql.lcdb.org

About This Documentation
========================

This comprehensive developer guide was created to provide in-depth technical documentation for experienced PHP developers working with the Doctrine ORM GraphQL library. It covers architecture, implementation details, performance considerations, and advanced usage patterns.

For user-focused documentation, see the official documentation at https://doctrine-orm-graphql.apiskeletons.dev
