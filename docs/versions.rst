==================================
Versions and Event Manager Support
==================================

The event manager used in this library is from `league/event <https://github.com/thephpleague/event>`_.
There are two supported versions of the event manager library by
`The PHP League <https://github.com/thephpleague>`_ and their API is very different.  In this library,
version 3 of `league/event` has always been used.  Version 3 is a PSR-14 compliant event manager.

However, `The PHP League <https://github.com/thephpleague>`_ does not use the latest version of their own
event manager in their `league/oauth2-server <https://github.com/thephpleague/oauth2-server>`_.  Because of this
old version requirement, it was not possible to install the ``league/oauth2-server`` library and this library in the
same project.  Version 11 of ``api-skeletons/doctrine-orm-graphql`` has regressive support for ``league/event``
by supporting version 2 of that library instead of version 3.  Version 2 is not PSR-14 compliant.

Version Overview
================

* **14.x** - In development; batch loading, stricter validation and many fixes (breaking changes from 13.x)
* **13.x** - Current version, QueryBuilder-based collection resolution (breaking changes from 12.x)
* **12.x** - Introduced QueryBuilder support, deprecated Criteria Event for collections
* **11.x** - Supports `league/event <https://github.com/thephpleague/event>`_ version 2.2 (non-PSR-14)

Version 14.x (In Development)
=============================

**Requirements**:

- PHP 8.4+
- Doctrine ORM 2.20.9+ or 3.0+
- doctrine/doctrine-laminas-hydrator 3.2+
- doctrine/inflector 2.0.4+, now a direct dependency
- league/event 3.0.3+ (PSR-14 compliant)
- webonyx/graphql-php 15.29+

On PHP 8.5, Doctrine ORM 3.3.1 or later is required.  ``doctrine/doctrine-laminas-hydrator``
supports PHP 8.5 only from 3.7.0, which requires ``doctrine/persistence`` 4, and earlier
ORM versions do not support ``doctrine/persistence`` 4.

**Key Features**:

- Associations are loaded in batches, so a nested query costs a fixed number of database
  queries however many rows it returns (``batchAssociations``, on by default, and ``batchLimit``)
- ``totalCount`` is counted only when it is requested
- ``first``, ``after``, ``last`` and ``before`` are top-level connection arguments, as in
  the Complete Connection Model, and every combination of them is well defined
- To-one associations are filtered by the identifier of the entity they refer to, with
  ``eq``, ``neq``, ``in``, ``notin`` and ``isnull``
- The ``useNonNullTypes`` config option makes identifiers and required columns and
  associations non-null types
- The ``formatJsonAs`` config option exchanges JSON as a document string or as the value
- Twelve more Doctrine types are mapped, including those of DBAL 4, and fields mapped with
  an ``enumType``
- Entity inheritance, embeddables, derived identities and a collection's
  ``#[ORM\OrderBy]`` are supported
- A QueryBuilder event listener may join collections, fetched or not; a page is still of
  entities
- Hydrator strategies receive the name of the field being extracted; ``ToString``
  hydrator strategy
- A computed field may be of an entity type, or a list of any type, and has an argument for
  each parameter of its method
- 14.1: a computed field with an ``expression``, its value in DQL, is filtered and sorted
  by it; see `computed fields <computed-fields.html#filters-and-sorting>`_
- 14.1: a filter's ``_or`` matches any of its branches, limited by the ``filterDepth``
  and ``filterConditions`` config; see `or <queries.html#or>`_
- 14.1: a computed field may be a method of the entity's repository, and one given a
  ``Collection`` of entities computes all their values with one query; see
  `repositories <computed-fields.html#repositories>`_
- Metadata is read as typed value objects, and cached metadata carries its format version
  and the config it was built with
- Configuration mistakes, such as a misplaced attribute, an invalid name or a field
  without a getter, are reported when the types are built
- A client sees only the errors its own request causes; see `errors <errors.html>`_

**Breaking Changes from 13.x**:

- Connections: the ``pagination`` argument is removed, as its fields are top-level
  arguments; connections are ordered by identifier after any other ordering, and a
  collection by its ``#[ORM\OrderBy]`` first
- Configuration: ``globalEnable`` and ``ignoreFields`` are removed; ``globalByValue`` and
  ``byValue`` are renamed ``extractByValue``; ``ConfigBuilder::sortFields()`` is renamed
  ``enableSortFields()``; ``sortFields`` is a ``bool`` and may not be ``null``; config
  values are validated
- Types: ``decimal`` is a ``String``; date-times are converted to the default timezone;
  the date and time scalars reject impossible and invalid values and serialize only date
  objects; filter, connection and input type names change; the ``sort`` filter takes the
  ``SortDirection`` enum
- Filters: comparing to null with ``eq``, ``neq``, ``in`` or ``notin`` is an error;
  ``LIKE`` wildcards match literally; ``bigint``, ``date_immutable`` and JSON fields have
  different filters; the inverse side of a one-to-one has no filter; a field's filters apply
  to that field only
- Mutations: an input without field lists makes the fields of nullable columns optional
- Validation: a misplaced attribute, an invalid GraphQL name, a duplicate field name, a
  negative limit, an association to an entity which is not exposed in the group, a field
  without a getter, or with one which requires a parameter, when extracting by value, and
  a wrong ``input()`` field list are errors; configuration checks no longer depend on
  ``zend.assertions``
- Errors: an error the developer must fix is shown to a client as
  ``Internal server error``
- Hydration: custom strategies may implement the new ``Strategy`` interface to receive the
  field name; the abstract ``Collection`` strategy is removed
- Metadata: cached metadata must be regenerated with ``Metadata::toArray()``
- Resolvers called directly return a null ``totalCount`` when it is not requested
- The ``Driver`` has no public properties, and its methods have specific return types
- ``symfony/var-exporter`` is no longer required
- See the `upgrade guide <upgrade.html>`_ for every change and its migration

Version 13.x (Current)
======================

**Requirements**:

- PHP 8.4+
- Doctrine ORM 2.20.9+ or 3.0+
- league/event 3.0+ (PSR-14 compliant)
- webonyx/graphql-php 15.29+

**Key Features**:

- QueryBuilder-based collection resolution (83% faster, 90% less memory)
- Removed Criteria Event for collections (breaking change)
- Database-level filtering with full index support
- Single query execution for collections
- PHP 8.4 Lazy Ghost object support

**Breaking Changes from 12.x**:

- Criteria Event removed for associations
- Must use QueryBuilder Event for collection filtering
- See `upgrade guide <upgrade.html>`_ for migration instructions

Version 12.x
============

**Requirements**:

- league/event 3.0+ (PSR-14 compliant)
- webonyx/graphql-php 15.0+ (15.29+ from 12.5)
- PHP and Doctrine ORM by minor version:

  - 12.0 to 12.3: PHP 8.1+, Doctrine ORM 2.18+ or 3.0+
  - 12.4: PHP 8.3+, Doctrine ORM 3.0+
  - 12.5: PHP 8.4+, Doctrine ORM 3.6+

**Key Features**:

- PSR-14 compliant event system
- Introduced QueryBuilder support for collections
- Shared PaginationService (eliminated code duplication)
- Criteria Event deprecated but still functional

**Deprecations**:

- Criteria Event for collections (removed in 13.x)

Version 11.x
============

**Requirements**:

- PHP 8.1+
- Doctrine ORM 2.18+ or 3.0+
- league/event 2.2 (non-PSR-14)
- webonyx/graphql-php 15.0+

**Compatibility**:

If you need to install ``league/oauth2-server`` and ``api-skeletons/doctrine-orm-graphql`` in the same project,
you must use version 11 of this library.

**Note**: Version 11.x uses in-memory Criteria for collection filtering, which has
performance limitations for large collections.

Choosing a Version
==================

**Use Version 13.x** (recommended) if:

- You want the best performance (83% faster collections)
- You can migrate Criteria Events to QueryBuilder Events
- You don't need ``league/oauth2-server`` compatibility

**Use Version 12.x** if:

- You need time to migrate from Criteria Events
- You want improved performance but with backward compatibility

**Use Version 11.x** if:

- You need ``league/oauth2-server`` compatibility
- You require league/event 2.2 for other dependencies


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
