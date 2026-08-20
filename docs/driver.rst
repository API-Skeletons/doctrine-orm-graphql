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
      'entityPrefix' => 'App\\ORM\\Entity\\',
      'group' => 'customGroup',
      'groupSuffix' => 'customGroupSuffix',
      'globalEnable' => true,
      'ignoreFields' => ['password'],
      'globalByValue' => true,
      'limit' => 500,
      'sortFields' => true,
      'useHydratorCache' => true,
      'useQueryResultCache' => true,
      'excludeFilters' => [Filters::LIKE],
  ]);


Config
======

The ``Driver`` takes a second, optional, argument of type
``ApiSkeletons\Doctrine\ORM\GraphQL\Config``.  The constructor of ``Config`` takes
an array parameter.

The parameter options are:


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


globalEnable
------------

When set to true, all fields and all associations will be
enabled.  This is best used as a development setting when
the entities are subject to change.  Really.


ignoreFields
------------

When ``globalEnable`` is set to true, this array of field and association names
will be excluded from the schema.  For instance ``['password']`` is a good choice
to ignore globally.


globalByValue
-------------

This overrides the ``byValue`` entity attribute globally.  When set to true
all hydrators will extract by value.  When set to false all hydrators will
extract by reference.  When not set the individual entity attribute value
is used and that is, by default, extract by value.


limit
-----

A hard limit for all queries throughout the entities.  Use this
to prevent abuse of GraphQL.  Default is 1000.


sortFields
----------

When entity types are created, and after the definition event,
the fields will be sorted alphabetically when set to true.
This can aid reading of the documentation created by GraphQL.


useHydratorCache
----------------

When set to true hydrator results will be cached for
the duration of the request thereby saving possible multiple extracts for
the same entity.  Default is ``false``


useQueryResultCache
-------------------

When set to true query results will be cached for
the duration of the request thereby preventing duplicate database queries
with identical SQL and parameters. This is particularly useful for:

- Circular references in the graph
- Queries accessing the same entity multiple times
- Duplicate association queries

The cache is request-scoped and automatically cleared after each request.
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
                    'args' => [
                        'pagination' => $driver->pagination(),
                    ],
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
        ->orderBy('name', 'ASC');

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
returns the resolve closure for that connection.  The ``pagination`` argument
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
                    'args' => [
                        'pagination' => $driver->pagination(),
                    ],
                    'resolve' => $driver->dbalResolve($queryBuilder),
                ],
            ],
        ]),
    ]);

When the query is resolved the QueryBuilder is given the offset and limit
calculated from the ``pagination`` argument.

Rows are returned as associative arrays so the fields of the given type are
resolved by their column name or alias.  Filters are not available because
there is no entity metadata to build them from.  The QueryBuilder is cloned
for each resolution so it is not modified by a query.

The ``totalCount`` is computed by replacing the select of the QueryBuilder
with ``COUNT(*)``.  A QueryBuilder using ``GROUP BY`` or ``DISTINCT`` will not
report the correct ``totalCount``.

The hard ``limit`` from the ``Config`` applies to these functions.


filter()
--------

Based on the attribute configuration of an entity, this function adds a
``filter`` argument to a connection.  See `filters <queries.html>`_ for a list of
available filters per field.  The args field must be ``filter``.

Filters are applied to a ``connection``.  It is also possible to use them ad-hoc
as detailed in `tips <tips.html>`_.

  .. code-block:: php

    'args' => [
        'pagination' => $driver->pagination(),
        'filter' => $driver->filter(Artist::class),
    ],


input()
-------

This function creates an InputObjectType for the given entity.  There are three
parameters:  The entity class name, an array of required fields, and an array
of optional fields.


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
