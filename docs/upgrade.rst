==============================
Upgrade from previous versions
==============================

13.0 to 13.2
============

Version 13.2 corrects the handling of the pagination arguments.  Every
combination of ``first``, ``last``, ``before`` and ``after`` is now well
defined and no argument is discarded when another is present.

Behaviour changes
-----------------

**Cursors for index zero are no longer read as absent arguments**

``{ before: "<the first cursor>" }`` previously returned a full page.  It now
returns an empty ``edges`` list, because no row precedes the first one.
Combined with ``last`` it previously returned the rows at the *end* of the
result set; it now returns nothing.

**Arguments are no longer discarded**

``{ after: "...", before: "..." }`` previously ignored ``before`` and
``{ last: n, after: "..." }`` previously ignored ``after``.  Both arguments now
narrow the range.  ``{ first: n, before: "..." }`` now returns the *first* ``n``
rows before the cursor rather than the last ``n``.

**A zero count returns no rows**

``{ first: 0 }`` previously returned a full page.  It now returns an empty
``edges`` list.

**Invalid arguments are reported**

A negative ``first`` or ``last``, or a cursor which cannot be decoded, was
previously coerced to something harmless and silently applied.  It is now
reported to the client as a GraphQL error of type
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Pagination``.

**A ``last`` larger than the result set no longer fails**

``{ last: 1000 }`` against a shorter collection previously produced
``Offset must be a positive integer or zero`` from Doctrine.  It now returns
every row.

Schema change
-------------

``PageInfo.startCursor`` and ``PageInfo.endCursor`` are now nullable ``String``
rather than ``String!``, matching the GraphQL Complete Connection Model.  They
are null when ``edges`` is empty.  Regenerate any client types built from the
schema.  A client which only feeds the cursors back as ``after`` or ``before``
needs no change.

Event change
------------

``Event\QueryBuilder::getOffset()`` and ``getLimit()`` are renamed to
``getRequestedOffset()`` and ``getRequestedLimit()``.

The event is dispatched before the rows are counted so a listener can modify
the QueryBuilder, so these values are the window the client requested rather
than the window finally queried.  A backward request - ``last`` without a
``before`` cursor - reports an offset of zero.

**Old (13.1)**:

.. code-block:: php

    function (QueryBuilder $event): void {
        $offset = $event->getOffset();
        $limit  = $event->getLimit();
    }

**New (13.2)**:

.. code-block:: php

    function (QueryBuilder $event): void {
        $offset = $event->getRequestedOffset();
        $limit  = $event->getRequestedLimit();
    }

``PaginationService``
---------------------

The shared pagination service changed shape.  Nothing else in the library calls
it, but if you use it directly:

* ``decodePaginationFields()`` returns ``int|null`` per field instead of ``int``
  so that an absent argument is distinct from index zero, and throws
  ``Exception\Pagination`` on invalid input.
* ``calculateOffsetAndLimit()`` takes ``int $itemCount`` as a required third
  argument instead of an optional nullable one.
* ``buildCursors()`` takes ``(int $offset, int $resultCount)``; the ``$itemCount``
  argument is gone and the returned array has ``start`` and ``end`` keys only.
* ``buildPaginationResponse()`` takes a fourth argument, ``int $offset``.
* ``calculateRequestedOffsetAndLimit()`` is new and produces the values the
  QueryBuilder event carries.

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
