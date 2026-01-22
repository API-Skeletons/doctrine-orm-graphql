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

* **13.x** - Current version, QueryBuilder-based collection resolution (breaking changes from 12.x)
* **12.x** - Introduced QueryBuilder support, deprecated Criteria Event for collections
* **11.x** - Supports `league/event <https://github.com/thephpleague/event>`_ version 2.2 (non-PSR-14)

Version 13.x (Current)
======================

**Requirements**:

- PHP 8.4+
- Doctrine ORM 3.6+
- league/event 3.0+ (PSR-14 compliant)
- webonyx/graphql-php 15.0+

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

- PHP 8.4+
- Doctrine ORM 3.6+
- league/event 3.0+ (PSR-14 compliant)
- webonyx/graphql-php 15.0+

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
- Doctrine ORM 3.0+
- league/event 2.2 (non-PSR-14)
- webonyx/graphql-php 14.0+

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
