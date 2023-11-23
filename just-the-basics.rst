Just The Basics
===============

The goal of this project is to get you up and running with GraphQL and Doctrine
as easily as possible.

You will need a Doctrine object manager with entities configured with
appropriate associations throughout.  Support for ad-hoc joins between
entities is not supported (but you can use the EntityDefinition event
in combination with the FilterQueryBuilder event to add a new field to
a Type with such ad-hoc support).  Your Doctrine metadata must contain all the
associations.  This requriement relates to the very basics of working in
Doctrine.

There are some `config options <config.html>`_ available but they are all optional.

The first step is to add attributes to your entities.  Attributes are a
feature of PHP 8.0 which act like annotations but are built into the language.
Attributes are stored in the namespace
``ApiSkeletons\Doctrine\ORM\GraphQL\Attribute`` and there are attributes for
``Entity``, ``Field``, and ``Association``.  Use the appropriate attribute on
each element you want to be queryable from GraphQL.

.. code-block:: php
  :linenos:

  <?php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field]
      private $id;

      #[GraphQL\Field]
      private $name;
  }

That's the minimum configuration requried.  Next, create your driver using your
entity manager

.. code-block:: php
  :linenos:

  <?php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

  $driver = new Driver($entityManager);

The next step is configuring your GraphQL schema.  In this section we'll create
types for the entity, the filter for the entity, and the resolver.

.. code-block:: php
  :linenos:

  <?php

  use GraphQL\Type\Definition\ObjectType;
  use GraphQL\Type\Definition\Type;
  use GraphQL\Type\Schema;

  $schema = new Schema([
      'query' => new ObjectType([
          'name' => 'query',
          'fields' => [
              'artist' => [
                  'type' => $driver->connection($driver->type(Artist::class)),
                  'args' => [
                      'filter' => $driver->filter(Artist::class),
                      'pagination' => $driver->pagination(),
                  ],
                  'resolve' => $driver->resolve(Artist::class),
              ],
          ],
      ]),
  ]);

Now, using the schema, you can start making GraphqL queries

.. code-block:: php
  :linenos:

  <?php

  use GraphQL\GraphQL;

  $query = '{ artist { edges { node { id name } } } }';

  $result = GraphQL::executeQuery($schema, $query);

If you want to add an association you must set attributes on the target entity.
In the following example the Artist entity has a one-to-many relationship with
Performance and we want to make deeper queries from Artist to Performance.

.. code-block:: php
  :linenos:

  <?php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field]
      private $id;

      #[GraphQL\Field]
      private $name;

      #[GraphQL\Association]
      private $performances;
  }

  #[GraphQL\Entity]
  class Performance
  {
      #[GraphQL\Field]
      private $id;

      #[GraphQL\Field]
      private $venue;
  }

Using the same Schema configuration as above, with the new Performance
attributes, a query of performances is now possible:

.. code-block:: php
  :linenos:

  <?php

  use GraphQL\GraphQL;

  $query = '{ artist { edges { node { id name performances { edges { node { id venue } } } } } } }';

  $result = GraphQL::executeQuery($schema, $query);

Keep reading to learn how to create multiple attribute groups, extract entities
by reference or by value, cache attribute metadata, enable custom types,
add documentation to every attribute, and more.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
