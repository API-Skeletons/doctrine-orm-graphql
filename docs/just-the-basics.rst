===============
Just The Basics
===============

You will need a Doctrine object manager with entities configured with
appropriate associations throughout.  Support for ad-hoc joins between
entities is not supported (but you can use the EntityDefinition event
to add a custom type to an entity type).
Your Doctrine metadata will map the associations in GraphQL.  

There are some `config options <driver.html#config>`_ available but they are
all optional.

The first step is to add attributes to your entities.
Attributes are stored in the namespace
``ApiSkeletons\Doctrine\ORM\GraphQL\Attribute`` and there are attributes for
``Entity``, ``Field``, and ``Association``.  Use the appropriate attribute on
each element you want to be queryable from GraphQL.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field]
      private $id;

      #[GraphQL\Field]
      private $name;
  }

That's the minimum configuration required.  Next, create your driver using your
entity manager

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

  $driver = new Driver($entityManager);

Then configure your GraphQL schema.  In this section we'll create
a connection for the entity, filters for the entity, and a resolver.

.. code-block:: php

  use GraphQL\Type\Definition\ObjectType;
  use GraphQL\Type\Definition\Type;
  use GraphQL\Type\Schema;

  $schema = new Schema([
      'query' => new ObjectType([
          'name' => 'query',
          'fields' => [
              'artist' => [
                  'type' => $driver->connection(Artist::class),
                  'args' => [
                      'filter' => $driver->filter(Artist::class),
                      'pagination' => $driver->pagination(),
                  ],
                  'resolve' => $driver->resolve(Artist::class),
              ],
          ],
      ]),
  ]);

Now, using the schema, you can start making GraphQL queries

.. code-block:: php

  use GraphQL\GraphQL;

  $query = '
    {
      artist {
        edges {
          node {
            id
            name
          }
        }
      }
    }
  ';

  $result = GraphQL::executeQuery($schema, $query);

If you want to add an association you must set attributes on the target entity.
In the following example, the Artist entity has a one-to-many relationship with
Performance and we want to make deeper queries from Artist to Performance.

.. code-block:: php

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

  use GraphQL\GraphQL;

  $query = '
    {
      artist {
        edges {
          node {
            id
            name
            performances {
              edges {
                node {
                  id
                  venue
                }
              }
            }
          }
        }
      }
    }
  ';

  $result = GraphQL::executeQuery($schema, $query);

Keep reading to learn how to create multiple attribute groups, extract entities
by reference or by value, cache attribute metadata, implement custom types,
alias fields, and more.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
