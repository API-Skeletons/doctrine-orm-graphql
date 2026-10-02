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

* ``type`` - **Required**. The GraphQL type name (e.g., ``'string'``, ``'int'``, ``'boolean'``),
  which must match a registered type in the TypeContainer, or the class of an entity; see
  `Entity Types`_.
* ``list`` - Optional. ``true`` when the method returns a list of the type, such as an
  array of strings or of entities.  The default is ``false``.
* ``description`` - Optional. A description of the computed field for GraphQL schema documentation.
* ``name`` - Optional. Override the field name in the GraphQL schema.  If not provided,
  the name is derived from the method name.
* ``group`` - Optional. The attribute group (default: ``'default'``).

Custom Field Names
------------------

By default, field names are derived from method names:

* ``getFullName()`` becomes ``fullName``
* ``isActive()`` stays ``isActive`` (for boolean methods)
* Other methods use the method name as-is, including a method such as
  ``getaway()`` whose name only begins with ``get``

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

* **Lazy evaluated** - The method is called only when the field is requested in a
  GraphQL query, and once for each entity however many times the query requests it
* **Integrated with the hydrator** - The hydrator's ``extract()`` returns computed values
  with the regular fields
* **Cached** - If you enable ``useHydratorCache``, a computed value is cached with the
  entity's other values for as long as the entity exists
* **Not filterable** - Computed fields cannot be used in database filters since they're
  calculated in PHP, not at the database level

Entity Types
------------

A computed field's ``type`` may be the class of an entity exposed in the
driver's group.  The field is then of that entity's type, and its method
returns an entity of the class, or ``null``.  With ``list: true`` the method
returns a list of them, such as an array or a Doctrine ``Collection``.

.. code-block:: php

  #[GraphQL\Entity]
  class Book
  {
      #[GraphQL\ComputedField(type: Author::class)]
      public function getWriter(): Author
      {
          return $this->author;
      }
  }

  #[GraphQL\Entity]
  class Author
  {
      #[GraphQL\ComputedField(type: Book::class, list: true)]
      public function getBestSellers(): array
      {
          return array_values($this->books->filter(
              static fn (Book $book): bool => $book->isBestSeller(),
          )->toArray());
      }
  }

This exposes a relation which is not mapped as an association, or which is
derived from one, or a subclass's own fields through its parent's type; see
`Inheritance <attributes.html#inheritance>`_.  The entity may be of the same class as the field's own
entity, and two entities may have computed fields of each other's types.  An
entity of a class which is not exposed in the group throws
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata`` when the metadata
is built.  The field's description is its own, else the entity's.

An entity which is not loaded, such as the target of an unloaded to-one
association, is loaded in a batch with the others, as an unloaded to-one
association is; so are the entities of a list.  The method itself is called
for each entity, so a method which reads a collection loads that collection
for each entity.  A computed field is not a connection: a list has no
pagination, filters or ``totalCount``, and it holds what the method returns.
For a large collection, expose the association instead, whose rows the
database filters and pages.

Filtering Limitations
---------------------

Computed fields do not appear in filter InputObjects because they cannot be filtered
at the database level.  If you need to filter on computed values, consider storing
them in the database or using the `QueryBuilder Event <events.html>`_ to add custom filters.

Hydrator Strategies
-------------------

Computed fields do not use `hydrator strategies <strategies.html>`_.  The value
returned by the entity method is used as-is; it is not passed through a strategy
and a strategy's ``$fieldName`` argument is never a computed field name.  Transform
the value inside the entity method instead.


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
