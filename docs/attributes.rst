==========
Attributes
==========

Configuration of your entities for GraphQL is done with PHP attributes.
There are three attributes and all options for each are covered in this
document.

The namespace for attributes is ``ApiSkeletons\Doctrine\ORM\GraphQL\Attribute``.
It is recommended you alias this namespace in your entities as ``GraphQL``.

A slightly complicated example:

.. code-block:: php

  use ApiSkeletons\Doctrine\GraphQL\Attribute as GraphQL

  #[GraphQL\Entity(description: 'Artist data', typeName: 'Artist')]
  #[GraphQL\Entity(group: 'admin', description: 'Artist data for admins')]
  class Artist
  {
      #[GraphQL\Field]
      #[GraphQL\Field(group: 'admin')]
      public $id;

      #[GraphQL\Field(description: 'Artist name', excludeCriteria: ['startswith'])]
      #[GraphQL\Field(group: 'admin')]
      public $name;

      #[GraphQL\Association(excludeCriteria: ['contains', 'neq'])]
      #[GraphQL\Association(group: 'admin')]
      public $performances;
  }


Entity
======

Use this attribute on entities you want included in your graph.
Optional parameters are:

* ``description`` - A description of the ``Entity``.
* ``excludeFilters`` - An array of Filters to exclude from available
  filters for all fields and associations in the entity.  For instance, to
  exclude filters that use a ``like`` database query, set the following::

    #[GraphQL\Entity(excludeCriteria: ['contains', 'startswith', 'endswith'])]

* ``group`` - You can have multiple GraphQL configurations organzied by
  ``group``.  Only one ``Entity`` attribute per group is allowed.
* ``includeFilters`` - An array of filters to include from available
  filters for all fields and associations in the entity.  ``includeCriteria``
  and ``excludeCriteria`` are mutually exclusive within one attribute.
* ``limit`` - A hard limit for all queries on this entity.  Use this
  to prevent abuse of GraphQL.  Defaults to global config ``limit``.
* ``typeName`` - A name to reference the type internal to GraphQL.

The following parameters are specific to the interal hydrator used to extract
data from Doctrine entities.  The hydrator library is
`doctrine-laminas-hydrator <https://github.com/doctrine/doctrine-laminas-hydrator>`_

* ``byValue`` - Default is ``true``.  When set to false the hydrator will
  extract values by reference.  If you have getters and setters for all your
  fields then extracting by value will use those.  Extracting by reference
  will reflect the entities and extract the values from the properties.
  More information here:
  `By Value and By Reference <https://www.doctrine-project.org/projects/doctrine-laminas-hydrator/en/3.0/by-value-by-reference.html#by-value-and-by-reference>`_
* ``hydratorFilters`` - Default is null.  An array of filters to apply to the
  hydrator.  In practice these should not be necessary because if you want to
  filter fields just don't include them in the attribute group.
  Filter classes must exist in the HydratorFactory container.  See `containers <containers.html>`_
* ``namingStrategy`` - Default is null.  You may set a naming strategy class.
  Class must exist in the HydratorFactory container.  See `containers <containers.html>`_


Field
=====

Use this attribute on fields (not associations) you want included
in your graph. Optional parameters are:

* ``description`` - A description of the ``Field``.
* ``excludeFilters`` - An array of filters to exclude from available
  filters for this field.  Combined with ``excludeFilters`` of the entity.
* ``group`` - You can have multiple GraphQL configurations organzied by
  ``group``.  Only one ``Field`` attribute per group is allowed.
* ``includeFilters`` - An array of filters to include from available
  filters for the field.  ``includeFilters``
  and ``excludeFilters`` are mutually exclusive within one attribute.
* ``hydratorStrategy`` - A custom hydrator strategy class.
  Class must be injected into the HydratorFactory container.  See `strategies <strategies.html>`_ and `containers <containers.html>`_
* ``type`` - Used for overriding the GraphQL type used for the field.
  The custom type must be injected into the TypeManager container.
  See `containers <containers.html>`_

.. code-block:: php

  // Handle a number field as a string

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field(type: 'customtype')]
      private int $number;
  }

  $driver = new Driver($this->getEntityManager());
  $driver->get(TypeManager::class)->set('customtype', fn() => Type::string());


Association
===========

Used on any type of association including one to one, one to many, many to one,
etc.  Associations which are to one types will just include the entity they are
associated with.  Associations of the to many variety will be filterable.

* ``description`` - A description of the ``Association``.
* ``excludeFilters`` - An array of criteria to exclude from available
  filters for the association. Entity level ``excludeFilters`` are applied to
  associations.  For instance, to exclude filters that use a ``like`` database
  query, set the following::

    #[GraphQL\Association(excludeCriteria: ['contains', 'startswith', 'endswith'])]

* ``criteriaEventName`` - An event to fire when resolving this collection.
  Additional filters can be added to the criteria.  An example of this use is for
  associations with soft deletes.
* ``group`` - You can have multiple GraphQL configurations organzied by
  ``group``.  Only one ``Association`` attribute per group is allowed.
* ``includeFilters`` - An array of filters to include from available
  filters for all fields in the association.  ``includeFilters``
  and ``excludeFilters`` are mutually exclusive within one attribute.
* ``limit`` - A limit for subqueries.  This value overrides the Entity configured
  limit.
* ``hydratorStrategy`` - A custom hydrator strategy class.
  Class must be injected into the HydratorFactory container.  See `containers <containers.html>`_

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
