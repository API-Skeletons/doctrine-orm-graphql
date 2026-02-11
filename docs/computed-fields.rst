===============
Computed Fields
===============

Computed fields allow you to expose derived values from entity methods in your GraphQL schema
without storing them in the database.  Common use cases include full names, formatted values,
calculations, and other business logic.

Using the ComputedField Attribute
==================================

The simplest way to add a computed field is with the ``#[ComputedField]`` attribute.
This attribute is placed on a public method in your entity.

Basic Example
-------------

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field]
      private string $firstName;

      #[GraphQL\Field]
      private string $lastName;

      #[GraphQL\ComputedField(
          type: 'string',
          description: 'Full name of the artist'
      )]
      public function getFullName(): string
      {
          return $this->firstName . ' ' . $this->lastName;
      }
  }

This creates a ``fullName`` field in your GraphQL schema that calls the ``getFullName()``
method when queried.  The field name is automatically derived from the method name
by removing the ``get`` prefix.

.. code-block:: graphql

  query {
    artists {
      edges {
        node {
          firstName
          lastName
          fullName
        }
      }
    }
  }

ComputedField Parameters
------------------------

The ``#[ComputedField]`` attribute accepts these parameters:

* ``type`` - **Required**. The GraphQL type name (e.g., ``'string'``, ``'int'``, ``'boolean'``).
  Must match a registered type in the TypeContainer.
* ``description`` - Optional. A description of the computed field for GraphQL schema documentation.
* ``name`` - Optional. Override the field name in the GraphQL schema.  If not provided,
  the name is derived from the method name.
* ``group`` - Optional. The attribute group (default: ``'default'``).

Custom Field Names
------------------

By default, field names are derived from method names:

* ``getFullName()`` becomes ``fullName``
* ``isActive()`` stays ``isActive`` (for boolean methods)
* Other methods use the method name as-is

You can override this with the ``name`` parameter:

.. code-block:: php

  #[GraphQL\ComputedField(
      type: 'string',
      name: 'displayName',
      description: 'Display name for UI'
  )]
  public function getFullDisplayName(): string
  {
      return 'Artist: ' . $this->name;
  }

This creates a ``displayName`` field instead of ``fullDisplayName``.

Multiple Computed Fields
-------------------------

You can add multiple computed fields to the same entity:

.. code-block:: php

  #[GraphQL\Entity]
  class User
  {
      #[GraphQL\Field]
      private string $name;

      #[GraphQL\Field]
      private string $email;

      #[GraphQL\ComputedField(type: 'string', description: 'Full name')]
      public function getFullName(): string
      {
          return $this->name . ' (' . $this->email . ')';
      }

      #[GraphQL\ComputedField(type: 'string', description: 'Email domain')]
      public function getEmailDomain(): string
      {
          return substr($this->email, strpos($this->email, '@') + 1);
      }

      #[GraphQL\ComputedField(type: 'boolean', description: 'Is user active')]
      public function isActive(): bool
      {
          return $this->password !== '';
      }
  }

How Computed Fields Work
-------------------------

Computed fields are:

* **Integrated with the hydrator** - Values are extracted along with regular fields
* **Cached per request** - If you enable ``useHydratorCache``, computed values are cached
* **Not filterable** - Computed fields cannot be used in database filters since they're
  calculated in PHP, not at the database level
* **Lazy evaluated** - Only computed when explicitly requested in a GraphQL query

Filtering Limitations
---------------------

Computed fields do not appear in filter InputObjects because they cannot be filtered
at the database level.  If you need to filter on computed values, consider storing
them in the database or using the `QueryBuilder Event <events.html>`_ to add custom filters.


Advanced: Event-Based Computed Fields
======================================

For more complex scenarios, such as computed fields that require database queries
or external service calls, you can use the `EntityDefinition Event <events.html>`_.

This approach gives you full control over the field definition and resolver logic.
You must attach a listener before defining your GraphQL schema.

Events of this type are named ``Entity::class . '.definition'`` and the event
name cannot be modified.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
  use App\ORM\Entity\Artist;
  use App\ORM\Entity\Performance;
  use Doctrine\ORM\EntityManager;
  use GraphQL\Type\Definition\ResolveInfo;
  use GraphQL\Type\Definition\Type;
  use League\Event\EventDispatcher;

  $driver = new Driver($entityManager);

  $driver->get(EventDispatcher::class)->subscribeTo(
      Artist::class . '.definition',
      static function (EntityDefinition $event) use ($driver): void {
          $definition = $event->getDefinition();

          // In order to modify the fields you must resolve the closure
          $fields = $definition['fields']();

          /**
           * Add a computed field to show the count of performances
           * This field will only be computed when it is requested specifically
           * in the query
           */
          $fields['performanceCount'] = [
              'type' => Type::int(),
              'description' => 'The count of performances for an Artist',
              'resolve' => static function (Artist $objectValue, array $args, $context, ResolveInfo $info) use ($driver): int {
                  $queryBuilder = $driver->get(EntityManager::class)->createQueryBuilder();
                  $queryBuilder
                     ->select('COUNT(performance)')
                     ->from(Performance::class, 'performance')
                     ->andWhere('performance.artist = :artistId')
                     ->setParameter('artistId', $objectValue->getId());

                  return (int) $queryBuilder->getQuery()->getSingleScalarResult();
              },
          ];

          // Assign modified fields array to the ArrayObject
          $definition['fields'] = $fields;
      }
  );

A query for this computed field:

.. code-block:: graphql

  query ArtistQueryWithComputedField($id: Int!)  {
    artist(id: $id) {
      id
      name
      performanceCount
    }
  }


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
