===============
Migration Guide
===============

This guide covers migration between major versions of the library.

Version 12.x (Current)
======================

Requirements
------------

- PHP 8.4+
- Doctrine ORM 3.6+
- league/event 3.0+ (PSR-14 compliant)
- webonyx/graphql-php 15.0+

Key Features
------------

- PSR-14 compliant event system
- QueryBuilder-based collection resolution (83% faster)
- Shared PaginationService (eliminated duplicate code)
- PHP 8.4 Lazy Ghost object support
- Database-level filtering (no in-memory Criteria)

Migrating from 11.x to 12.x
============================

Event System Changes
--------------------

**Version 11.x** used league/event 2.2 (non-PSR-14):

.. code-block:: php

    // 11.x
    use League\Event\Emitter;
    
    $emitter = $driver->get(Emitter::class);
    $emitter->addListener('event.name', function($event) {
        // ...
    });

**Version 12.x** uses league/event 3.0 (PSR-14):

.. code-block:: php

    // 12.x
    use League\Event\EventDispatcher;
    
    $dispatcher = $driver->get(EventDispatcher::class);
    $dispatcher->subscribeTo('event.name', function($event) {
        // ...
    });

**Migration Steps**:

1. Replace ``Emitter`` with ``EventDispatcher``
2. Replace ``addListener()`` with ``subscribeTo()``
3. Events must implement ``HasEventName`` interface

Collection Resolution Changes
------------------------------

**Version 11.x** used Criteria for collection filtering:

- Loaded entire collection into memory
- Filtered with Doctrine Criteria (in-memory)
- Triple iteration bug

**Version 12.x** uses QueryBuilder:

- Database-level filtering
- Single query execution
- 83% faster, 90% less memory

**Impact**: Most code continues to work unchanged. Performance improvements are automatic.

**Breaking Change**: CriteriaEvent deprecated for collections (still functional):

.. code-block:: php

    // 11.x - CriteriaEvent
    #[GraphQL\Association(criteriaEventName: 'artist.performances.criteria')]
    
    $driver->get(Emitter::class)->addListener(
        'artist.performances.criteria',
        function (CriteriaEvent $event) {
            $event->getCriteria()->andWhere(...);
        }
    );

.. code-block:: php

    // 12.x - QueryBuilderEvent (recommended)
    #[GraphQL\Association(criteriaEventName: 'artist.performances.query')]
    
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.performances.query',
        function (QueryBuilderEvent $event) {
            $event->getQueryBuilder()->andWhere(...);
        }
    );

Configuration Changes
---------------------

No breaking changes in Config options. All 11.x configurations work in 12.x.

**New in 12.x**: None (all features via code improvements)

Attribute Changes
-----------------

No changes. All attributes work identically in 12.x.

Deprecations
------------

**Soft Deprecated** (still functional):

- ``CriteriaEvent`` for collections (use ``QueryBuilderEvent`` instead)

**Removal Timeline**: CriteriaEvent will be removed in version 13.x

PHP Version Requirements
------------------------

**11.x**: PHP 8.1+

**12.x**: PHP 8.4+

**Migration**: Upgrade PHP to 8.4 or later

Dependency Updates
------------------

Update composer.json:

.. code-block:: json

    {
        "require": {
            "api-skeletons/doctrine-orm-graphql": "^12.0",
            "php": ">=8.4",
            "doctrine/orm": "^3.6",
            "league/event": "^3.0"
        }
    }

Run:

.. code-block:: bash

    composer update api-skeletons/doctrine-orm-graphql

Testing After Migration
-----------------------

1. **Run All Tests**: Ensure existing tests pass

   .. code-block:: bash

       vendor/bin/phpunit

2. **Check Event Listeners**: Update from Emitter to EventDispatcher

3. **Profile Performance**: Verify performance improvements

4. **Review Logs**: Check for deprecation warnings

5. **Test GraphQL Queries**: Execute sample queries

Step-by-Step Migration
======================

Step 1: Update Dependencies
----------------------------

.. code-block:: bash

    # Update composer.json
    composer require api-skeletons/doctrine-orm-graphql:^12.0
    composer require league/event:^3.0
    
    # Update PHP if needed
    # (requires PHP 8.4+)

Step 2: Update Event Listeners
-------------------------------

Search and replace in your codebase:

**Find**:

.. code-block:: php

    use League\Event\Emitter;
    $driver->get(Emitter::class)->addListener(

**Replace**:

.. code-block:: php

    use League\Event\EventDispatcher;
    $driver->get(EventDispatcher::class)->subscribeTo(

Step 3: Update CriteriaEvent Listeners
---------------------------------------

**Optional but recommended** - update collection event listeners:

**Find**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria;
    
    function (Criteria $event) {
        $event->getCriteria()->andWhere(...);
    }

**Replace**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;
    
    function (QueryBuilder $event) {
        $event->getQueryBuilder()->andWhere(...);
    }

Step 4: Test Thoroughly
------------------------

.. code-block:: bash

    # Run tests
    vendor/bin/phpunit
    
    # Manual testing
    # Execute GraphQL queries and verify results

Step 5: Update Documentation
-----------------------------

Update internal documentation to reference:

- EventDispatcher (not Emitter)
- QueryBuilder events for collections
- New performance characteristics

Common Issues
=============

Issue: Event Listeners Not Firing
----------------------------------

**Cause**: Still using Emitter instead of EventDispatcher

**Solution**:

.. code-block:: php

    // Wrong
    $driver->get(Emitter::class)->addListener(...);
    
    // Correct
    $driver->get(EventDispatcher::class)->subscribeTo(...);

Issue: CriteriaEvent Deprecated Warning
----------------------------------------

**Cause**: Using old CriteriaEvent for collections

**Solution**: Update to QueryBuilderEvent (see Step 3)

Issue: PHP Version Incompatibility
-----------------------------------

**Cause**: Running on PHP < 8.4

**Solution**: Upgrade to PHP 8.4 or stay on version 11.x

Rollback Plan
=============

If migration fails, rollback:

.. code-block:: bash

    composer require api-skeletons/doctrine-orm-graphql:^11.0
    composer require league/event:^2.2

Restore old code from version control.

Future Versions
===============

Version 13.x (Planned)
----------------------

**Expected Changes**:

- Remove CriteriaEvent completely
- PHP 8.5+ requirement
- Additional performance optimizations

**Timeline**: TBD

Staying Updated
---------------

- Monitor GitHub releases: https://github.com/API-Skeletons/doctrine-orm-graphql/releases
- Read CHANGELOG.md
- Join discussions: https://github.com/API-Skeletons/doctrine-orm-graphql/discussions

Support
=======

For migration issues:

- GitHub Issues: https://github.com/API-Skeletons/doctrine-orm-graphql/issues
- Documentation: https://doctrine-orm-graphql.apiskeletons.dev

Summary
=======

Key migration points:

1. Update PHP to 8.4+
2. Replace Emitter with EventDispatcher
3. Replace addListener() with subscribeTo()
4. Optional: Update CriteriaEvent to QueryBuilderEvent
5. Test thoroughly

Performance benefits are automatic after migration!
