======================================
Doctrine ORM GraphQL - Developer Docs
======================================

Comprehensive Technical Documentation
======================================

This directory contains comprehensive technical documentation for the Doctrine ORM GraphQL library, written specifically for experienced PHP developers.

Documentation Structure
=======================

Core Documentation
------------------

**index.rst**
    Main entry point with library overview, quick start, and architecture summary.

**architecture.rst**
    Deep dive into the library's internal architecture:

    - Container system and dependency injection
    - Lazy Ghost object initialization
    - Metadata extraction and caching
    - Query resolution flow
    - Type system internals
    - Hydration strategies
    - Filter application
    - Event dispatching

API Reference
-------------

**driver-reference.rst**
    Complete API reference for the Driver class:

    - Constructor and configuration
    - Type methods (type, connection)
    - Query methods (resolve, filter, pagination)
    - Mutation methods (input)
    - Container methods
    - Common patterns and examples

**config-reference.rst**
    Detailed configuration options:

    - All Config parameters explained
    - Use cases and examples
    - Configuration patterns
    - Best practices
    - Common pitfalls

**attributes-reference.rst** (TODO)
    PHP attribute reference:

    - #[Entity] attribute
    - #[Field] attribute
    - #[Association] attribute
    - #[ExcludeFilters] attribute
    - Examples and patterns

**filters-reference.rst** (TODO)
    Filter system documentation:

    - Available filter types
    - Filter generation
    - QueryBuilder application
    - Custom filters
    - Performance considerations

**events-reference.rst** (TODO)
    Event system documentation:

    - PSR-14 event dispatching
    - EntityDefinition events
    - QueryBuilder events
    - Metadata events
    - Custom event names
    - Event listener patterns

**types-reference.rst** (TODO)
    Type system documentation:

    - Built-in GraphQL types
    - Custom scalar types
    - Entity type generation
    - Connection types
    - Input types
    - Type registry

Guides
------

**performance.rst**
    Performance optimization guide:

    - Query optimization strategies
    - Database indexing
    - Configuration tuning
    - Caching strategies
    - Profiling tools
    - Real-world examples
    - Performance benchmarks

**advanced-topics.rst** (TODO)
    Advanced usage patterns:

    - Custom resolvers
    - Custom types
    - Hydration strategies
    - Multiple schemas
    - Framework integration
    - Testing strategies

**internals.rst** (TODO)
    Deep internals documentation:

    - Source code structure
    - Service initialization
    - Type building process
    - Query execution pipeline
    - Memory management
    - Thread safety

**migration-guide.rst** (TODO)
    Migration between versions:

    - 11.x to 12.x migration
    - Breaking changes
    - Deprecations
    - New features

About This Documentation
========================

Target Audience
---------------

This documentation is written for experienced PHP developers who:

- Have working knowledge of Doctrine ORM
- Understand GraphQL concepts
- Want to understand the library's internals
- Need to optimize performance
- Want to extend the library

Differences from Official Docs
-------------------------------

**Official Documentation** (https://doctrine-orm-graphql.apiskeletons.dev):

- User-focused
- Getting started guides
- Common use cases
- Examples and tutorials

**This Documentation**:

- Developer-focused
- Architecture deep-dives
- Performance optimization
- Internal implementation details
- Advanced patterns

Use Official Docs For:
    - Getting started
    - Basic usage
    - Common patterns
    - Tutorials

Use These Docs For:
    - Understanding internals
    - Performance tuning
    - Advanced customization
    - Contributing to the library

Contributing to This Documentation
===================================

This documentation was generated using the library's source code and existing documentation as reference. To update:

1. Read the source code (``src/`` directory)
2. Review existing tests (``test/`` directory)
3. Reference CLAUDE.md for project-specific guidance
4. Update the relevant .rst file
5. Submit a pull request

Documentation Standards
-----------------------

- Use reStructuredText format
- Include code examples for all concepts
- Explain the "why" not just the "what"
- Reference source code locations
- Include performance considerations
- Add real-world examples

Building Documentation
======================

These .rst files can be built using Sphinx:

.. code-block:: bash

    # Install Sphinx
    pip install sphinx sphinx-rtd-theme

    # Build HTML documentation
    sphinx-build -b html claude_docs/ claude_docs/_build/html

    # View in browser
    open claude_docs/_build/html/index.html

Configuration
-------------

Create a ``conf.py`` file in this directory:

.. code-block:: python

    project = 'Doctrine ORM GraphQL - Developer Guide'
    copyright = '2025, API Skeletons'
    author = 'API Skeletons'

    extensions = [
        'sphinx.ext.autodoc',
        'sphinx.ext.intersphinx',
        'sphinx.ext.viewcode',
    ]

    templates_path = ['_templates']
    exclude_patterns = ['_build', 'Thumbs.db', '.DS_Store']

    html_theme = 'sphinx_rtd_theme'
    html_static_path = ['_static']

    intersphinx_mapping = {
        'php': ('https://www.php.net/manual/en/', None),
        'doctrine': ('https://www.doctrine-project.org/projects/doctrine-orm/en/latest/', None),
    }

Quick Reference
===============

Most Important Files
--------------------

1. **index.rst** - Start here for overview
2. **architecture.rst** - Understand how it works
3. **driver-reference.rst** - API reference
4. **config-reference.rst** - Configuration options
5. **performance.rst** - Optimization strategies

Common Tasks
------------

**Understanding the Architecture**:
    Read architecture.rst sections on Container System and Query Resolution

**Optimizing Performance**:
    Read performance.rst, especially Query Optimization and Database Optimization sections

**Configuring the Driver**:
    Read config-reference.rst for all available options

**Using the Driver API**:
    Read driver-reference.rst for method signatures and examples

**Extending the Library**:
    Read advanced-topics.rst (TODO) for extensibility points

Version Information
===================

This documentation corresponds to:

- Library Version: 12.x
- PHP Version: 8.4+
- Doctrine ORM: 3.6+
- Last Updated: January 2025

Links
=====

- GitHub: https://github.com/API-Skeletons/doctrine-orm-graphql
- Official Docs: https://doctrine-orm-graphql.apiskeletons.dev
- Packagist: https://packagist.org/packages/api-skeletons/doctrine-orm-graphql
- Live Example: https://graphql.lcdb.org

License
=======

This documentation is part of the Doctrine ORM GraphQL library and is licensed under the MIT License.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
