================
The Driver class
================

The Driver class is the gateway to much of the functionality of this library.
It has many options and top-level functions, detailed here.


Creating a Driver with all config options
=========================================

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

  $driver = new Driver($entityManager, new Config([
      'batchAssociations' => true,
      'batchLimit' => 1000,
      'entityPrefix' => 'App\\ORM\\Entity\\',
      'group' => 'customGroup',
      'groupSuffix' => 'customGroupSuffix',
      'extractByValue' => true,
      'limit' => 500,
      'sortFields' => true,
      'useHydratorCache' => true,
      'useQueryResultCache' => true,
      'excludeFilters' => [Filters::CONTAINS],
  ]);


Config
======

The ``Driver`` takes a second, optional, argument of type
``ApiSkeletons\Doctrine\ORM\GraphQL\Config``.  The constructor of ``Config`` takes
an array parameter.

The parameter options are:


batchAssociations
-----------------

When set to true, associations are loaded in batches rather than once for
each row.  Default is ``true``.

* The unloaded to-one associations of a field are loaded with one query per
  entity class.
* The rows of a one-to-many or many-to-many collection field are counted with
  one query for all the rows it is resolved for, and fetched with one more
  (two for many-to-many), within the ``batchLimit``.

A query then costs a fixed number of queries however many rows it returns.
The results are the same as without batching.

A collection is not batched when its association has an ``eventName``, so its
`QueryBuilder event <events.html>`_ is still dispatched for each row, or when
its source or target entity has a composite identifier.


batchLimit
----------

The most rows a batched collection field fetches with one query.  When the
rows of all the rows a collection field is resolved for number more than
this, each row's page is queried separately; they are still counted with one
query.  Default is 1000.


entityPrefix
------------

This is a common namespace prefix for all entities in a group.  When specified,
the ``entityPrefix`` such as, 'App\\ORM\\Entity\\', will be stripped from driver name.  So
``App_ORM_Entity_Artist_groupName``
becomes
``Artist_groupName``
See also ``groupSuffix``


excludeFilters
--------------

An array of filters to exclude from all available filters for all fields
and associations for all entities.


extractByValue
--------------

This overrides the ``extractByValue`` entity attribute globally.  When set to true
all hydrators will extract by value.  When set to false all hydrators will
extract by reference.  When not set the individual entity attribute value
is used and that is, by default, extract by value.


group
-----

Each attribute has an optional ``group`` parameter that allows
for multiple configurations within the entities.  Specify the group in the
``Config`` to load only those attributes with the same ``group``.
If no ``group`` is specified the group value is ``default``.


groupSuffix
-----------

By default, the group name is appended to GraphQL types.  You may specify
a different suffix or an empty suffix.  When used in combination with
``entityPrefix`` your type names can be changed from
``App_ORM_Entity_Artist_groupname``
to
``Artist``


limit
-----

The most rows a connection returns, whatever pagination is requested.  Use
this to prevent abuse of GraphQL.  Default is 1000.

This is the default limit.  An entity's ``limit`` replaces it for queries of
that entity, and an association's ``limit`` replaces both for that
association, even when larger.  See `attributes <attributes.html>`_.


sortFields
----------

When entity types are created, and after the definition event,
the fields will be sorted alphabetically when set to true.
This can aid reading of the documentation created by GraphQL.


useHydratorCache
----------------

When set to true hydrator results will be cached for as long as the entity
they were extracted from exists, thereby saving possible multiple extracts for
the same entity.  An entity is normally kept by Doctrine until the entity
manager is cleared, so clear it between requests in a long running process.
Values of an entity changed after it was extracted are not seen until it is
freed.  Default is ``false``


useQueryResultCache
-------------------

When set to true query results will be cached, thereby preventing duplicate
database queries with identical SQL and parameters. This is particularly
useful for:

- Circular references in the graph
- Queries accessing the same entity multiple times
- Duplicate association queries

The results are entities of the entity manager, so the cache is cleared
whenever the entity manager is cleared or flushed.  A mutation that flushes
therefore does not leave stale results.  Clear the entity manager between
requests in a long running process, or the cache, like the entity manager,
keeps results from earlier requests.  Changes made outside the entity manager,
such as by another process, are not seen until the cache is cleared.  The
cache can also be cleared directly:

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;

  $driver->get(QueryResultCache::class)->clear();

Performance benefits are most noticeable with complex, nested GraphQL queries
that may execute the same database query multiple times.

Default is ``false``

**Note**: This caches the query results, not the hydrated entities.
For entity caching, see ``useHydratorCache``.


Functions
=========

completeConnection()
--------------------

This is a short cut to using connection(), pagination(), resolve(), and filter().
There are three parameters:

1. Doctrine entity class name, required,
2. entityDefinitionEvent name, optional.
3. queryBuilderEvent name, optional.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

    $driver = new Driver($this->getEntityManager());

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => $driver->completeConnection(Artist::class),
            ],
        ]),
    ]);


connection(), pagination(), and resolve()
-----------------------------------------

The ``connection`` function returns a wrapper for an entity type.  This wrapper,
in combination with the ``resolve`` and ``pagination`` functions, implements the
`GraphQL Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_.
You may pass a second parameter to the ``connection`` function to specify the
custom event name to fire for the entity definition event.


  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

    $driver = new Driver($this->getEntityManager());

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => [
                    'type' => $driver->connection(Artist::class),
                    'args' => $driver->pagination(),
                    'resolve' => $driver->resolve(Artist::class),
                ],
            ],
        ]),
    ]);


dbalCompleteConnection()
------------------------

This is a short cut to using dbalConnection(), pagination(), and dbalResolve().
There are two parameters:

1. A GraphQL ``ObjectType`` describing one row of the result, required,
2. A ``Doctrine\DBAL\Query\QueryBuilder``, required.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

    $driver = new Driver($this->getEntityManager());

    $queryBuilder = $entityManager->getConnection()->createQueryBuilder()
        ->select('id', 'name')
        ->from('artist')
        ->orderBy('name', 'ASC')
        ->addOrderBy('id', 'ASC');

    $artistRow = new ObjectType([
        'name' => 'artistRow',
        'fields' => [
            'id' => Type::int(),
            'name' => Type::string(),
        ],
    ]);

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artistRows' => $driver->dbalCompleteConnection($artistRow, $queryBuilder),
            ],
        ]),
    ]);


dbalConnection() and dbalResolve()
----------------------------------

These functions implement the
`GraphQL Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_
for a
`DBAL QueryBuilder <https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/query-builder.html>`_.
They are for queries which are not backed by an entity such as reports and
aggregates.

The ``dbalConnection`` function takes a GraphQL ``ObjectType`` describing one
row of the result and returns that type wrapped in a connection.  The
``dbalResolve`` function takes a ``Doctrine\DBAL\Query\QueryBuilder`` and
returns the resolve closure for that connection.  The pagination arguments
must be added to the args.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

    $driver = new Driver($this->getEntityManager());

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artistRows' => [
                    'type' => $driver->dbalConnection($artistRow),
                    'args' => $driver->pagination(),
                    'resolve' => $driver->dbalResolve($queryBuilder),
                ],
            ],
        ]),
    ]);

When the query is resolved the QueryBuilder is given the offset and limit
calculated from the pagination arguments.

Each page is a separate query with its own offset, so the rows must be in the
same order every time the query runs.  Give the QueryBuilder an ``ORDER BY``
which orders every row, ending with a column or columns which are unique, such
as a primary key.  A database returns rows in no particular order without an
``ORDER BY``, and in no particular order within a tie, so rows may be repeated
or skipped from one page to the next.  An entity connection is ordered by its
identifier for this reason, but a DBAL query has no identifier the driver can
use.

Rows are returned as associative arrays so the fields of the given type are
resolved by their column name or alias.  Filters are not available because
there is no entity metadata to build them from.  The QueryBuilder is cloned
for each resolution so it is not modified by a query.

The ``totalCount`` is computed by counting the rows of the QueryBuilder's
query, without its offset, limit and ordering, as a subquery, so a query using
``GROUP BY``, ``DISTINCT`` or ``HAVING`` is counted by the rows it returns.

The ``limit`` from the ``Config`` applies to these functions.


filter()
--------

Based on the attribute configuration of an entity, this function adds a
``filter`` argument to a connection.  See `filters <queries.html>`_ for a list of
available filters per field.  The args field must be ``filter``.

Filters are applied to a ``connection``.  It is also possible to use them ad-hoc
as detailed in `tips <tips.html>`_.

  .. code-block:: php

    'args' => ['filter' => $driver->filter(Artist::class)] + $driver->pagination(),


input()
-------

This function creates an InputObjectType for the given entity.  There are four
parameters:  The entity class name, an array of required fields, an array
of optional fields, and an optional name for the type.  See
`mutations <mutations.html>`_.


type()
------

This function returns GraphQL types for all Doctrine types, any custom types,
and Doctrine entity types.

There are two type containers:  ``TypeContainer`` and ``EntityTypeContainer``.
Types from each of these containers are returned from this `type()` function.

See `types <types.html>`_ for details on custom types and using the ``TypeContainer``.

The ``EntityTypeContainer`` is used only for Doctrine entities and is populated
though the `metadata <metadata.html>`_.  This class is used internally for generating ``ObjectType`` types for entities.

Though a ``connection`` is a type, it is not
available through this function.  Use the ``connection`` function of the Driver.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
