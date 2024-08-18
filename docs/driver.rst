================
The Driver class
================

The Driver class is the gateway to much of the functionality of this library.
It has many options and top-level functions, detailed here.

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


Creating a ``Driver`` with all config options
---------------------------------------------

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

  $driver = new Driver($entityManager, new Config[
      'entityPrefix' => 'App\\ORM\\Entity\\',
      'group' => 'customGroup',
      'groupSuffix' => 'customGroupSuffix',
      'globalEnable' => true,
      'ignoreFields' => ['password'],
      'globalByValue' => true,
      'limit' => 500,
      'sortFields' => true,
      'useHydratorCache' => true,
      'excludeFilters' => [Filters::LIKE],
  ]);


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
