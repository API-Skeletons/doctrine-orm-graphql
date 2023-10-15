Driver Config
=============

The ``Driver`` takes a second, optional, argument of type
``ApiSkeletons\Doctrine\GraphQL\Config``.  The constructor of ``Config`` takes
an array parameter.

The parameter options are:


entityPrefix
----------------

This is a common namespace prefix for all entities in a group.  When specified,
the ``entityPrefix`` such as, 'App\\ORM\\Entity\\', will be stripped from driver name.  So
``App_ORM_Entity_Artist_groupName``
becomes
``Artist_groupName``
See also ``groupSuffix``


group
--------- 

Each attribute has an optional ``group`` parameter that allows
for multiple configurations within the entities.  Specify the group in the
``Config`` to load only those attributes with the same ``group``.
If no ``group`` is specified the group value is ``default``.


groupSuffix
---------------

By default, the group name is appended to GraphQL types.  You may specify
a different suffix or an empty suffix.  When used in combination with
``entityPrefix`` your type names can be changed from 
``App_ORM_Entity_Artist_groupname``
to 
``Artist``


globalEnable
----------------

When set to true all fields and all associations will be
enabled.  This is best used as a development setting when
the entities are subject to change.  

.. note:: The strategy
   ``NullifyOwningAssociation`` is not automatically applied to many
   to many relationships when using ``globalEnable``.


globalIgnore
----------------

When ``globalEnable`` is set to true, this array of field and associations names
will be excluded from the schema.  For instance ``['password']`` is a good choice
to ignore globally.


globalByValue
-----------------

This overrides the ``byValue`` entity attribute globally.  When set to true 
all hydrators will extract by value.  When set to false all hydrators will
extract by reference.  When not set the individual entity attribute value
is used and that is, by default, extract by value.


limit
----- 

A hard limit for all queries throughout the entities.  Use this
to prevent abuse of GraphQL.  Default is 1000.


excludeCriteria
---------------

An array of filters to exclude from all available filters for all fields and associations for all entities.


sortFields
----------

When entity types are created, and after the definition event,
the fields will be sorted alphabetically when set to true.
This can aid reading of the documentation created by GraphQL.


useHydratorCache
-------------------- 

When set to true hydrator results will be cached for
the duration of the request thereby saving multiple extracts for
the same entity.  **Default is ``false``**.

Creating a ``Driver`` with all config options:

.. code-block:: php
  :linenos:

  <?php

  use ApiSkeletons\Doctrine\GraphQL\Config;
  use ApiSkeletons\Doctrine\GraphQL\Driver;

  $driver = new Driver($entityManager, new Config[
      'entityPrefix' => 'App\\ORM\\Entity\\',
      'group' => 'customGroup',
      'groupSuffix' => 'customGroupSuffix',
      'globalEnable' => true,
      'globalIgnore' => ['password'],
      'globalByValue' => true,
      'limit' => 500,
      'sortFields' => true,
      'useHydratorCache' => true,
  ]);


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
