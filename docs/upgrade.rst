==============================
Upgrade from previous versions
==============================

13.x to 14.0
============

Impossible dates are rejected
-----------------------------

The ``Date``, ``DateTime`` and ``DateTimeTZ`` scalars, and their immutable
versions, accepted a date or time which does not exist and rolled it over:
``2004-02-31`` became 2004-03-02 and ``T25:00:00`` the next day.  Such a value
is now an error.

LIKE filters match wildcards literally
--------------------------------------

``contains``, ``startswith`` and ``endswith`` passed their value into a LIKE
pattern unescaped, so ``%`` and ``_`` in the value acted as wildcards:
``contains: "%"`` matched every non-null value.  They are now escaped and match
only themselves.  A client which relied on sending wildcards must use another
filter.

The query result cache is cleared with the entity manager
---------------------------------------------------------

With ``useQueryResultCache`` enabled, cached results were kept for the life of
the driver.  They were served after the entity manager was cleared and after a
mutation flushed, so a long running process returned stale, detached entities
and the cache grew without limit.  The cache is now cleared whenever the entity
manager is cleared or flushed.  Clear the entity manager between requests, as
a long running process should already do.

Cached metadata carries a format version
----------------------------------------

Metadata is now cached with ``$driver->get(Metadata::class)->toArray()``,
which adds a ``__version`` key to the array, rather than ``getArrayCopy()``.
The ``Driver`` requires that key when it is given cached metadata, and throws
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata`` without it.

* Regenerate metadata caches written by 13.x or with ``getArrayCopy()``.
* Change the caching code to use ``toArray()``; see `metadata <metadata.html>`_.
* Add ``'__version' => Metadata::FORMAT_VERSION`` to metadata you write by
  hand.

The shape of each entity's metadata is unchanged.

globalByValue is renamed extractByValue
---------------------------------------

The ``globalByValue`` config option is renamed ``extractByValue``, matching
``ConfigBuilder::extractByValue()``, and ``Config::getGlobalByValue()`` is
renamed ``Config::getExtractByValue()``.  Its meaning is unchanged.  The old
name throws ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration``.

.. code-block:: php

    // 13.x
    new Config(['globalByValue' => false]);

    // 14.0
    new Config(['extractByValue' => false]);

symfony/var-exporter is no longer required
------------------------------------------

This library never used ``symfony/var-exporter``, so it is no longer a
requirement.  Doctrine ORM 3 still requires it and installs it.  If you use
Doctrine ORM 2 with lazy ghost objects enabled, which need it, require
``symfony/var-exporter`` in your own project.

Hydrator strategies receive the field name
------------------------------------------

This library now has its own
``ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\Strategy`` interface, which
extends ``Laminas\Hydrator\Strategy\StrategyInterface``.  Its ``extract()`` method
takes a third argument, the Doctrine field name being extracted:

.. code-block:: php

    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed;

All strategies supplied with this library implement the new interface.

The abstract ``Hydrator\Strategy\Collection`` strategy is removed.  It was a
copy of Doctrine's internal ``AbstractCollectionStrategy``.  A custom strategy
for a collection-valued association must implement
``Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface`` and this
library's ``Strategy`` interface, as ``AssociationDefault`` does.

Custom strategies which implement only the Laminas interface continue to work
unchanged and are called without the field name.  See
`Hydrator Strategies <strategies.html>`_.

Pagination arguments are top-level arguments
--------------------------------------------

The ``pagination`` argument is removed.  ``first``, ``after``, ``last`` and
``before`` are now top-level arguments of every connection, as the
`GraphQL Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_
defines them.  This is a schema change.

**Clients** must move the pagination fields out of the ``pagination`` object:

.. code-block:: js

    # 13.x
    artists (filter: { name: { contains: "Dead" } }, pagination: { first: 10, after: "MA==" }) { ... }

    # 14.0
    artists (filter: { name: { contains: "Dead" } }, first: 10, after: "MA==") { ... }

A query which still uses ``pagination`` fails validation with an unknown
argument error.  Regenerate any client types built from the schema.

**Schemas built by hand**: ``Driver::pagination()`` now returns an array of the
four argument definitions instead of the ``Pagination`` input type.  Add it to
the top level of the args rather than under a ``pagination`` key:

.. code-block:: php

    // 13.x
    'args' => [
        'filter' => $driver->filter(Artist::class),
        'pagination' => $driver->pagination(),
    ],

    // 14.0
    'args' => ['filter' => $driver->filter(Artist::class)] + $driver->pagination(),

``completeConnection()``, ``dbalCompleteConnection()`` and the arguments of
association fields are updated for you.

The ``Pagination`` input type (``Type\Pagination``) is removed, so
``$driver->type('pagination')`` no longer exists.

**QueryBuilder event listeners**: ``$event->getArgs()['pagination']`` is now
always null.  Read ``getArgs()['first']``, ``['after']``, ``['last']`` and
``['before']`` instead.  This change does not raise an error, so search your
listeners for ``'pagination'``.

Type names for custom event names are valid GraphQL names
---------------------------------------------------------

An entity type created with a custom event name, through ``$driver->type()``,
``connection()`` or ``completeConnection()``, was named
``<type name>.<event name>``.  A ``.`` is not valid in a GraphQL name, so the
schema failed ``assertValid()``, schema printing and client code generation.

The event name is now joined with an underscore, and any character which is
not valid in a GraphQL name is replaced with an underscore:
``artist_default.artist.custom`` becomes ``artist_default_artist_custom``.
The ``Node_`` and ``Connection_`` types change with it.  The event dispatched
is unchanged.  Regenerate any client types built from the schema.

Aliases apply to to-one associations
------------------------------------

An ``alias`` on a to-one association (``ManyToOne`` or ``OneToOne``) was
ignored when naming the GraphQL field, which kept the association name and
always resolved to null.  The field and its ``eq`` filter are now named by the
alias, as they already were for collections and fields.

If you set an ``alias`` on a to-one association, query and filter it by the
alias.  This is a schema change for those fields; regenerate any client types
built from the schema.

Configuration errors are checked in production
----------------------------------------------

Several configuration checks used ``assert()``, which is disabled in
production when ``zend.assertions`` is ``-1``.  With assertions disabled a
duplicate attribute was silently accepted, the last one winning.  These checks
now throw in every environment:

* Two ``#[Entity]``, ``#[Field]``, ``#[Association]`` or ``#[ComputedField]``
  attributes for the same group on one entity, field, association or method
  throw ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata``, which was
  an ``AssertionError``.
* A ``hydratorStrategy`` which does not implement
  ``Laminas\Hydrator\Strategy\StrategyInterface`` throws
  ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator``.
* Building a type with ``TypeContainer::build()`` from a class which does not
  implement ``Buildable`` throws
  ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration``.
* A ``#[ComputedField]`` whose name collides with a field throws
  ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata``, which was a
  ``RuntimeException``.

If your development environment ran with assertions enabled you have already
seen these errors.  Otherwise, fix any duplicate attributes they report.

Date and time scalars are strict about their values
----------------------------------------------------

The ``Date``, ``DateImmutable``, ``DateTime``, ``DateTimeImmutable``,
``DateTimeTZ``, ``DateTimeTZImmutable``, ``Time`` and ``TimeImmutable``
scalars now serialize any ``DateTimeInterface``, mutable or immutable, and
throw ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization``
for anything else.  Six of them previously returned a string unchanged and
returned null for any other value, including the other ``DateTimeInterface``
class.  If a resolver or computed field returns a formatted string for one of
these types, return the date object instead.

A ``Time`` or ``TimeImmutable`` value written as a literal in a query is now
validated, as a variable already was.  An invalid time literal is an error.

Associations are loaded in batches
----------------------------------

Associations are now loaded in batches, which is on by default.  Unloaded
to-one associations are loaded with one query per entity class, and a
collection field is counted and fetched with a fixed number of queries
however many rows it is resolved for.  Artists with their performances take
4 queries instead of 10, and two levels of collections 6 instead of 22.  The
results are the same.

A collection whose association has an ``eventName`` is not batched, so its
QueryBuilder event is still dispatched for each row with that row as
``getObjectValue()``.

Resolvers now return ``GraphQL\Deferred`` for batched fields, which
``GraphQL::executeQuery()`` resolves.  If you execute queries with your own
promise adapter, it must support ``Deferred``.

To turn batching off, set ``'batchAssociations' => false``.  See
`batchAssociations and batchLimit <driver.html#batchassociations>`_.

Many-to-many collections return their own members
-------------------------------------------------

A many-to-many collection on the owning side returned every row of the target
entity for every source, and one on the inverse side failed with a Doctrine
semantic error.  Both now return the members of the source's own collection.

Connections are ordered by identifier
-------------------------------------

Entity connections and collections had no ``ORDER BY`` unless the ``sort``
filter was used.  SQL does not guarantee the order of rows without one, so
pages fetched with ``first`` and ``after`` could overlap or skip rows.

The identifier is now always the last ordering: rows without a ``sort`` are
returned in identifier order, and rows which sort equally are ordered by
identifier.  Ordering added by a QueryBuilder event listener comes before the
identifier, so it still decides the order.

Field filters apply to their own field only
-------------------------------------------

The ``includeFilters`` and ``excludeFilters`` of one ``#[Field]`` also removed
those filters from every field of the entity processed after it.  A field now
has the entity's filters limited only by its own attribute, so some fields
gain filters they were missing.  This is a schema change: the filter input
types of those fields gain fields and their generated names change.
Regenerate any client types built from the schema.

Date and time filters match date and time fields
------------------------------------------------

Filter values for date and time fields are bound as the field's Doctrine
type.  A filter on a ``date``, ``date_immutable``, ``time`` or
``time_immutable`` field previously matched no rows, because the value was
bound as a ``datetime``.  No change is needed; these filters now work.

The sort filter takes a SortDirection enum
------------------------------------------

The ``sort`` filter was a ``String``.  Any value was passed to Doctrine, so an
invalid direction failed inside the query with a DQL error.  It is now the
``SortDirection`` enum with the values ``ASC`` and ``DESC``, and an invalid
value is rejected when the query is validated.  This is a schema change.

Enum values are not quoted, and they are upper case:

.. code-block:: js

    # 13.x
    filter: { name: { sort: "asc" } }

    # 14.0
    filter: { name: { sort: ASC } }

Variables still pass the value as a string, ``{"direction": "ASC"}``, and are
declared with the ``SortDirection`` type.  Lower case ``asc`` and ``desc``,
which were accepted before, are rejected.

If you use more than one driver in a schema, share the ``TypeContainer``
between them as described in `tips <tips.html>`_, as you do for ``PageInfo``.

``Filter\Filters::type()`` now takes the ``TypeContainer`` as a second
parameter, from which the shared ``SortDirection`` type is fetched.

Input type names are stable
---------------------------

Input types were named ``<type>_Input_<uniqid>``, which changed on every
build.  They are now named ``<type>_Input`` when no field lists are given and
``<type>_Input_<hash of the fields>`` otherwise, so they are the same on every
build.  Calling ``input()`` again with the same entity and fields returns the
same type instead of a new one.

``input()`` takes a new optional fourth parameter to name the type yourself,
for example ``$driver->input(Artist::class, ['name'], [], 'CreateArtistInput')``.

Regenerate any client types built from the schema.

input() rejects field names it cannot use
-----------------------------------------

``$driver->input()`` previously ignored a name in the required or optional
list which was not a field of the entity, such as a typo or an association
name.  It now throws ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input``,
suggesting the closest exposed field.  Naming a field which is not exposed in
the driver's group also throws; it previously failed with a ``TypeError``.

The exception is thrown when the input type is first used, normally while the
schema is built.  Remove any names it reports from your ``input()`` calls.

globalEnable and ignoreFields are removed
-----------------------------------------

The ``globalEnable`` and ``ignoreFields`` config options are removed, along
with ``ConfigBuilder::globalEnable()``, ``ConfigBuilder::ignoreFields()``,
``ConfigBuilder::ignoreField()``, ``Config::getGlobalEnable()``,
``Config::getIgnoreFields()`` and the ``Metadata\GlobalEnable`` class.

Passing either option to ``Config`` now throws
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration``.  Expose
entities, fields and associations with the ``#[Entity]``, ``#[Field]`` and
``#[Association]`` attributes instead.

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

If you use ``criteriaEventName`` in your ``#[Association]`` attributes, rename
it to ``eventName`` and update your event listeners:

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

    #[GraphQL\Association(eventName: Artist::class . '.performances')]
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
