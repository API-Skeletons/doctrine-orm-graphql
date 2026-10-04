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
* ``args`` - Optional. The type of each method parameter which is not an ``int``,
  ``float``, ``string`` or ``bool``, by its name; see `Arguments`_.
* ``description`` - Optional. A description of the computed field for GraphQL schema documentation.
* ``name`` - Optional. Override the field name in the GraphQL schema.  If not provided,
  the name is derived from the method name.
* ``group`` - Optional. The attribute group (default: ``'default'``).
* ``expression`` - Optional. The DQL expression of the field's value, by which it is
  filtered and sorted; see `Filters and Sorting`_.
* ``excludeFilters`` - Optional. Filters to exclude for a field with an ``expression``.
* ``includeFilters`` - Optional. The only filters to allow for a field with an
  ``expression``.  It is mutually exclusive with ``excludeFilters``.

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
  GraphQL query, and once for each entity however many times the query requests it.
  A field with `arguments`_ is computed once for each entity and set of arguments.
* **Integrated with the hydrator** - The hydrator's ``extract()`` returns computed values
  with the regular fields, except those of fields with arguments, which have no one value
* **Cached** - If you enable ``useHydratorCache``, a computed value is cached with the
  entity's other values for as long as the entity exists
* **Filtered by an expression** - A computed field's value is calculated in PHP, so it is
  filtered and sorted only when it has an ``expression``, its value in DQL; see
  `Filters and Sorting`_

Arguments
---------

A computed field has an argument for each parameter of its method, of the
same name:

.. code-block:: php

  #[GraphQL\ComputedField(type: 'int')]
  public function getTotalRecordings(int|null $year = null): int
  {
      if ($year === null) {
          return $this->recordings->count();
      }

      return $this->recordings->filter(
          static fn (Recording $recording): bool => $recording->getYear() === $year,
      )->count();
  }

.. code-block:: graphql

  { artist { edges { node { name totalRecordings } } } }
  { artist { edges { node { name totalRecordings(year: 2002) } } } }

GraphQL arguments are named, so the argument is ``year``, not a position.

* An ``int``, ``float``, ``string`` or ``bool`` parameter is an ``Int``,
  ``Float``, ``String`` or ``Boolean`` argument.  Give the type of any other
  parameter, by its name, in the attribute's ``args``, as a type registered in
  the TypeContainer:

  .. code-block:: php

    #[GraphQL\ComputedField(type: 'boolean', args: ['date' => 'date_immutable'])]
    public function getFoundedBefore(DateTimeImmutable $date): bool

* A parameter which does not allow null is a required, non-null argument,
  unless it has a default value.
* A default value is the argument's default, and must be an ``int``,
  ``float``, ``string`` or ``bool``.  An argument which is not given, and has
  no default, is null.
* A variadic parameter, or one passed by reference, cannot be an argument.

These are checked when the metadata is built, and throw
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata``.  When the type
is built, an argument's type must be one which can be input, and a default
must be a value of it: ``'2020-01-01'`` is not a value of ``date_immutable``,
whose values are dates, so a parameter given that type in ``args`` may not
have it as its default.  These throw the same exception.

Each set of arguments has its own value, so aliases of a field with
different arguments, such as ``a: totalRecordings(year: 2002)
b: totalRecordings(year: 2003)``, each have theirs.  A computed field with
arguments has no one value, so the hydrator's ``extract()`` leaves it out.

The method is called for each entity a query returns.  A method which
queries the database, rather than reading the entity, runs a query for each
of them.

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
`Inheritance <attributes.html#inheritance>`_.  The entity may be of the same
class as the field's own entity, and two entities may have computed fields of
each other's types.  An
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

Filters and Sorting
-------------------

A computed field's value is calculated in PHP, after the rows are fetched, but
a filter must be applied by the database: the rows of a page, and its
``totalCount``, are counted and limited there.  So a computed field is filtered
and sorted only when it has an ``expression``: the DQL expression of its value.
The field's filters, ``sort`` and ``sortPriority`` among them, then apply to the
expression as a field's apply to its column.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\ComputedField(
          type: 'int',
          expression: '(SELECT COUNT({p}.id) FROM App\ORM\Entity\Performance {p} WHERE {p}.artist = {entity})',
      )]
      public function getPerformanceCount(): int
      {
          return $this->performances->count();
      }
  }

.. code-block:: graphql

  {
    artists (filter: { performanceCount: { gt: 0, sort: DESC } }) {
      edges { node { name performanceCount } }
    }
  }

The method still computes the value a query returns; the expression is used
only to filter and sort.  They must compute the same value, or a filter will
disagree with the value shown, and a sorted list will appear out of order.

Placeholders
~~~~~~~~~~~~

.. important::

  **Every alias in an expression must be a placeholder**, written in braces:
  ``{entity}`` for the field's own entity, and any other name, such as
  ``{p}``, for an alias of the expression's own.

DQL aliases must be unique across a query, subqueries included, and an
expression may be used more than once in one query: by two filters, such as
``{ gt: 0, lt: 5 }``, or by a filter and a sort.  Each placeholder is given a
name unique in the query each time the expression is used, so its aliases
never collide with each other's, or with those of a QueryBuilder listener.  An
alias written plainly, such as ``p``, works until the expression is used twice;
the check described below finds it.

A placeholder is any ``{name}`` in the expression, including one within a
string literal.

Arguments
~~~~~~~~~

``{:name}`` in an expression is the field's `argument <#arguments>`_
``name``, bound as a parameter.  A filter is applied before any field is
resolved, so its arguments cannot be those the field is given where it is
selected.  The field's filters take them instead, in ``args``, of the
arguments the expression uses:

.. code-block:: php

  #[GraphQL\ComputedField(
      type: 'int',
      expression: '(SELECT COUNT({r}.id) FROM App\ORM\Entity\Recording {r} '
          . 'WHERE {r}.artist = {entity} AND {r}.year >= {:year})',
  )]
  public function getRecordingCountSince(int $year): int

.. code-block:: graphql

  {
    artists (filter: { recordingCountSince: { args: { year: 2003 }, gt: 0 } }) {
      edges { node { name recordingCountSince(year: 2003) } }
    }
  }

An argument not given in ``args`` is its default value, or null when it has
none.  ``args`` is required when an argument the expression uses is.  The
filter's ``args`` and the field's own arguments are independent: a filter may
use 2003 while the field shows 2004.

Which Filters Apply
~~~~~~~~~~~~~~~~~~~

The filters of a computed field are those of its ``type``, less those
excluded by the configuration, by its entity, by the association whose
filters they are, and by its own ``excludeFilters`` or ``includeFilters``.

DQL compares any expression, but takes a subquery, or an arithmetic
expression, for none of ``IN``, ``IS NULL`` and ``LIKE``.  So the ``in``,
``notin``, ``isnull``, ``contains``, ``startswith`` and ``endswith`` filters
are each excluded for an expression DQL does not take them for, and added to
the field's ``excludeFilters`` in the metadata.  ``between`` is applied as two
comparisons, so it applies to every expression.  A function, such as
``UPPER({entity}.name)`` or ``SIZE({entity}.performances)``, takes every
filter.

A sort orders by the expression selected as a hidden result variable, as DQL
does not order by a subquery.  A `QueryBuilder listener <events.html>`_ must
not replace the query's select, as ``->select()`` does, which would remove it.

Checks
~~~~~~

Each expression is checked when the metadata is built, after the
``metadata.build`` event, and throws
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata`` when:

* it is not valid DQL, such as for a syntax error, an unknown field or class,
  or an alias written without a placeholder.  Doctrine's ``QueryException`` is
  the previous exception.  The expression is parsed, not run, so no database
  is needed.
* it uses ``{:name}`` for a name which is not a parameter of the method.
* its field is a ``list``, or of an entity type, neither of which has a single
  value to filter or sort by.
* a field without an ``expression`` has ``excludeFilters`` or
  ``includeFilters``.

Metadata read from a cache is not checked again.

Costs
~~~~~

* A subquery is evaluated for each row the filters consider, and a sort by one
  orders every row before the page is taken, without an index.  For a large
  table, store the value in a column instead.
* Databases order nulls differently, as they do for a field.

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
