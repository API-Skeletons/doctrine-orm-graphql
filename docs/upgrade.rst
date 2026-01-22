==============================
Upgrade from previous versions
==============================

12.x to 13.x
============

Version 13.x introduces significant performance improvements through QueryBuilder-based
collection resolution, but requires migration of association event listeners.

Breaking Changes
----------------

**Collection Event System Changed**

The Criteria Event system for filtering associations has been replaced with QueryBuilder
Events. This change provides:

* 83% faster query execution for filtered collections
* 90% reduction in memory usage
* Database-level filtering with full index support
* Single query execution instead of in-memory filtering

**Migration Required**

If you use ``criteriaEventName`` in your ``#[Association]`` attributes, you must
update your event listeners:

**Old (12.x)**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria;

    #[GraphQL\Association(criteriaEventName: Artist::class . '.performances.criteria')]
    public $performances;

    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.performances.criteria',
        function (Criteria $event): void {
            $event->getCriteria()->andWhere(
                $event->getCriteria()->expr()->eq('venue', 'Delta Center')
            );
        }
    );

**New (13.x)**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;

    #[GraphQL\Association(criteriaEventName: Artist::class . '.performances')]
    public $performances;

    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.performances',
        function (QueryBuilder $event): void {
            $event->getQueryBuilder()
                ->andWhere('entity.venue = :venue')
                ->setParameter('venue', 'Delta Center');
        }
    );

**Migration Checklist**:

1. Replace ``Event\Criteria`` with ``Event\QueryBuilder`` in use statements
2. Remove ``.criteria`` suffix from event names in attributes and listeners
3. Replace ``$event->getCriteria()`` with ``$event->getQueryBuilder()``
4. Convert Criteria expressions to QueryBuilder syntax:

   * ``$criteria->expr()->eq('field', 'value')`` → ``'entity.field = :param'`` + ``setParameter()``
   * ``$criteria->expr()->gt('field', 10)`` → ``'entity.field > :param'`` + ``setParameter()``
   * ``$criteria->expr()->in('field', [1,2,3])`` → ``'entity.field IN (:param)'`` + ``setParameter()``

5. Use ``entity`` as the default alias in WHERE clauses

Performance Impact
------------------

After upgrading, you will automatically benefit from:

* Faster collection queries (no performance tuning required)
* Reduced memory consumption for large collections
* Better database resource utilization

See the `technical documentation <technical/performance.html>`_ for detailed
performance benchmarks.

9.x to 10.x
===========

The ``$driver->connection()`` function no longer takes an ObjectType for an
entity.  Instead, just pass the entity class name.


8.1.3 doctrine-graphql
======================

This repository, ``api-skeletons/doctrine-orm-graphql`` is a continuation of
``api-skeletons/doctrine-graphql`` but there are some changes necessary to
move from the old repository to this new one.


Namespaces
----------

The old namespace was ``ApiSkeletons\Doctrine\GraphQL`` and the new namespace
is ``ApiSkeletons\Doctrine\ORM\GraphQL``.  This is the only change between
the repositories that should affect you.

The namespace change was made to be more technically correct (the best kind
of correct) as each repository only supports ORM and does not support ODM.


Documentation
-------------

With the new repository the documentation was reviewed in whole and corrected
where necessary.  There is a new theme for the documentation, leaving the old ReadTheDocs default behind.  And, though the documentation is still hosted by https://readthedocs.org it has been moved to a new
domain: https://doctrine-orm-graphql.apiskeletons.dev


What to do?
-----------

As a user of the old repository version 8.1.3, change your namespaces to the
new namespace then replace your composer require to ``api-skeletons/doctrine-orm-graphql ^8.1`` and you will be upgraded to the new repository version 8.1.4.
