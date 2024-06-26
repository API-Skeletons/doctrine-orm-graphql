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

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL
  use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

  #[GraphQL\Entity(description: 'Artist data', typeName: 'Artist')]
  #[GraphQL\Entity(group: 'admin', description: 'Artist data for admins')]
  class Artist
  {
      #[GraphQL\Field]
      #[GraphQL\Field(group: 'admin')]
      public $id;

      #[GraphQL\Field(description: 'Artist name', excludeFilters: [Filters::STARTSWITH])]
      #[GraphQL\Field(group: 'admin')]
      public $name;

      #[GraphQL\Association(excludeFilters: [Filters::CONTAINS, Filters::NEQ])]
      #[GraphQL\Association(group: 'admin', alias: 'shows')]
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
    
    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    #[GraphQL\Entity(excludeFilters: [Filters::CONTAINS, Filters::STARTSWITH, Filters::ENDSWITH])]

* ``group`` - You may have multiple GraphQL configurations organzied by
  ``group``.
* ``includeFilters`` - An array of filters to include from available
  filters for all fields and associations in the entity.  ``includeFilters``
  and ``excludeFilters`` are mutually exclusive.
* ``limit`` - A hard limit for all queries on this entity.  Use this
  to prevent abuse of GraphQL.  Defaults to global config ``limit``.
* ``typeName`` - A name to reference the type for GraphQL.

The following parameters are specific to the hydrator used to extract
data from Doctrine entities.  The hydrator library is
`doctrine-laminas-hydrator <https://github.com/doctrine/doctrine-laminas-hydrator>`_

* ``byValue`` - Default is ``true``.  When set to false the hydrator will
  extract values by reference.  If you have getters and setters for all your
  fields then extracting by value will use those.  Extracting by reference
  will reflect the entities and extract the values from the properties.
  More information here:
  `By Value and By Reference <https://www.doctrine-project.org/projects/doctrine-laminas-hydrator/en/3.0/by-value-by-reference.html#by-value-and-by-reference>`_


Field
=====

Use this attribute on fields (not associations) you want included
in your graph. Optional parameters are:

* ``alias`` - An alias to use as the GraphQL field name.
* ``description`` - A description of the ``Field``.
* ``excludeFilters`` - An array of filters to exclude from available
  filters for this field.  Combined with ``excludeFilters`` of the entity.
* ``group`` - You can have multiple GraphQL configurations organzied by
  ``group``.
* ``includeFilters`` - An array of filters to include from available
  filters for the field.  ``includeFilters``
  and ``excludeFilters`` are mutually exclusive.
* ``hydratorStrategy`` - A custom hydrator strategy class.
  Class must be injected into the HydratorFactory container.  See `strategies <strategies.html>`_ and `containers <containers.html>`_
* ``type`` - Override the GraphQL type name for the field.
  The custom type must be injected into the TypeContainer
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
  $driver->get(TypeContainer::class)->set('customtype', fn() => Type::string());


Association
===========

Used on any type of association including one to one, one to many, many to one,
etc.  Associations which are to one types will just include the entity they are
associated with.  Associations of the to many variety will become connections.

* ``alias`` - An alias to use as the GraphQL field name.
* ``description`` - A description of the ``Association``.
* ``excludeFilters`` - An array of criteria to exclude from available
  filters for the association. Entity level ``excludeFilters`` are applied to
  associations.  For instance, to exclude filters that use a ``like`` database
  query, set the following::

    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

    #[GraphQL\Association(excludeFilters: [Filters::CONTAINS, Filters::STARTSWITH, Filters::ENDSWITH])]

* ``criteriaEventName`` - An event to fire when resolving this collection.
  Additional filters can be added to the criteria.  An example of this use is for
  associations with soft deletes.
* ``group`` - You can have multiple GraphQL configurations organzied by
  ``group``.
* ``includeFilters`` - An array of filters to include from available
  filters for all fields in the association.  ``includeFilters``
  and ``excludeFilters`` are mutually exclusive.
* ``limit`` - A limit for subqueries.  This value overrides the Entity configured
  limit.
* ``hydratorStrategy`` - A custom hydrator strategy class.
  Class must be injected into the HydratorFactory container.  See `containers <containers.html>`_

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
