===============
Migration Guide
===============

This guide covers migration between major versions of the library.

Version 13.x (Current)
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
- QueryBuilder-based collection resolution (83% faster, 90% less memory)
- Shared PaginationService (eliminated duplicate code)
- PHP 8.4 Lazy Ghost object support
- Database-level filtering with full index support
- **Breaking**: Removed Criteria Event for collections (replaced with QueryBuilder Event)

Migrating from 12.x to 13.x
============================

Breaking Changes
----------------

**CriteriaEvent Removed for Collections**

The primary breaking change in 13.x is the removal of the Criteria Event system
for collection filtering. Collections now use QueryBuilder exclusively for
database-level filtering.

**Why This Change?**

- **Performance**: 83% faster query execution, 90% less memory usage
- **Scalability**: Database-level filtering with proper index support
- **Efficiency**: Single query execution instead of loading entire collections into memory

Migration Steps
---------------

**Step 1: Update Event Import**

.. code-block:: php

    // 12.x - OLD
    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria;

    // 13.x - NEW
    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;

**Step 2: Update Event Names**

Remove ``.criteria`` suffix from association event names:

.. code-block:: php

    // 12.x - OLD
    #[GraphQL\Association(criteriaEventName: Artist::class . '.performances.criteria')]
    public $performances;

    // 13.x - NEW
    #[GraphQL\Association(criteriaEventName: Artist::class . '.performances')]
    public $performances;

**Step 3: Update Event Listeners**

Convert Criteria expressions to QueryBuilder syntax:

**12.x - OLD Approach**:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.performances.criteria',
        function (Criteria $event): void {
            $event->getCriteria()->andWhere(
                $event->getCriteria()->expr()->eq('venue', 'Delta Center')
            );
        }
    );

**13.x - NEW Approach**:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.performances',
        function (QueryBuilder $event): void {
            $event->getQueryBuilder()
                ->andWhere('entity.venue = :venue')
                ->setParameter('venue', 'Delta Center');
        }
    );

Common Criteria to QueryBuilder Conversions
--------------------------------------------

**Equality**:

.. code-block:: php

    // OLD
    $event->getCriteria()->expr()->eq('field', 'value')

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.field = :field')
        ->setParameter('field', 'value');

**Greater Than / Less Than**:

.. code-block:: php

    // OLD
    $event->getCriteria()->expr()->gt('price', 100)

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.price > :price')
        ->setParameter('price', 100);

**IN Array**:

.. code-block:: php

    // OLD
    $event->getCriteria()->expr()->in('id', [1, 2, 3])

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.id IN (:ids)')
        ->setParameter('ids', [1, 2, 3]);

**NULL Checks**:

.. code-block:: php

    // OLD
    $event->getCriteria()->expr()->isNull('deletedAt')

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.deletedAt IS NULL');

**LIKE / Contains**:

.. code-block:: php

    // OLD
    $event->getCriteria()->expr()->contains('name', 'search')

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.name LIKE :name')
        ->setParameter('name', '%search%');

**Multiple Conditions**:

.. code-block:: php

    // OLD
    $event->getCriteria()
        ->andWhere($event->getCriteria()->expr()->eq('active', true))
        ->andWhere($event->getCriteria()->expr()->gt('price', 50));

    // NEW
    $event->getQueryBuilder()
        ->andWhere('entity.active = :active')
        ->andWhere('entity.price > :price')
        ->setParameter('active', true)
        ->setParameter('price', 50);

Testing After Migration
------------------------

1. **Run All Tests**:

   .. code-block:: bash

       vendor/bin/phpunit

2. **Test GraphQL Queries**: Execute queries that use filtered associations

3. **Profile Performance**: Verify performance improvements with large collections

4. **Check Event Listeners**: Ensure all association filters work correctly

Performance Benefits
--------------------

After migration, you'll experience:

- **Query Execution**: 83% faster for filtered collections
- **Memory Usage**: 90% reduction for large collections
- **Database Load**: Better index utilization, fewer full table scans
- **Scalability**: Can handle larger datasets without memory issues

Version 12.x
============

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
- **Deprecated**: Criteria Event for collections (still functional, removed in 13.x)

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

**Version 12.x** introduced QueryBuilder support:

- Database-level filtering
- Single query execution
- 83% faster, 90% less memory

**Impact**: Criteria Event still works in 12.x but is deprecated for collections.

**Deprecation Notice**: CriteriaEvent for collections is deprecated in 12.x and removed in 13.x.

.. code-block:: php

    // 11.x and 12.x - CriteriaEvent (deprecated in 12.x, removed in 13.x)
    #[GraphQL\Association(criteriaEventName: 'artist.performances.criteria')]

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.performances.criteria',
        function (Criteria $event) {
            $event->getCriteria()->andWhere(...);
        }
    );

.. code-block:: php

    // 12.x and 13.x - QueryBuilderEvent (recommended)
    #[GraphQL\Association(criteriaEventName: 'artist.performances')]

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.performances',
        function (QueryBuilder $event) {
            $event->getQueryBuilder()->andWhere(...);
        }
    );

**Migration Path**: If migrating from 11.x to 12.x, you can keep using CriteriaEvent
temporarily, but should plan to migrate to QueryBuilderEvent before upgrading to 13.x.

Configuration Changes
---------------------

No breaking changes in Config options. All 11.x configurations work in 12.x.

**New in 12.x**: None (all features via code improvements)

Attribute Changes
-----------------

No changes. All attributes work identically in 12.x.

Deprecations in 12.x
--------------------

**Deprecated** (still functional in 12.x, removed in 13.x):

- ``CriteriaEvent`` for collections (use ``QueryBuilderEvent`` instead)

**Action Required**: Migrate to QueryBuilderEvent before upgrading to 13.x

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
