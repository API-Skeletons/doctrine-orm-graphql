=================
Running Mutations
=================

Mutations modify data in your Doctrine ORM.  They are defined as such:

.. code-block:: php

  $schema = new Schema([
      'mutation' => new ObjectType([
          'name' => 'mutation',
          'fields' => [
              'mutationName' => [
                  'type' => $driver->type(Artist::class),
                  'args' => [
                      'id' => Type::nonNull(Type::id()),
                      'input' => Type::nonNull($driver->input(Artist::class, ['name'])),
                  ],
                  'resolve' => function ($root, $args) use ($driver): User {
                      $artist = $driver->get(EntityManager::class)
                          ->getRepository(Artist::class)
                          ->find($args['id']);

                      $artist->setName($args['input']['name']);
                      $driver->get(EntityManager::class)->flush();

                      return $artist;
                  },
              ],
          ],
      ]),
  ]);

You can define multiple mutations under the ``fields`` array.  The ``type`` is
the GraphQL type of the entity you're processing and will return.  The ``args`` array in this
example has a traditional argument and an ``input`` argument.  The ``input``
argument is created using the driver ``$driver->input(Entity::class)`` method and
has two optional arguments.  The ``resolve`` method passes the ``args`` to
a function that will do the work.  In this example that function returns an
``Artist`` entity thereby allowing a query on the result.


Calling Mutations
=================

.. code-block:: php

  $query = 'mutation MutationName($id: Int!, $name: String!) {
      mutationName(id: $id, input: { name: $name }) {
          id
          name
      }
  }';

To call a mutation you must prefix the request with ``mutation``.  The mutation
will then take input from the ``args`` array.  The ``id`` and ``name`` in this
mutation will return the new values from the mutated entity.


Input Argument
==============

The driver function ``$driver->input(Entity::class)`` will return an
``InputObjectType`` with every exposed field.  A field whose column is not
nullable is required and a field whose column is nullable is optional.  There
are two optional parameters to specify required and optional fields.

.. code-block:: php

  $driver->input(Entity::class, ['requiredField'], ['optionalField'])

In the above mutation example the ``name`` field is required and there are no
optional fields, so the only field in the ``input`` args will be ``name``.
The ``name`` input field will be typed according to its metadata configuration.

A field with an ``alias`` is named by its alias in the input, and may be
named by its alias or its field name in the lists.  A field may not be in both
lists.

Identifiers are excluded from the input field list because they should not be
changed or added by a user.

Only fields exposed with a ``#[Field]`` attribute in the driver's group can be
input.  When no field lists are given, a column which is not exposed, such as a
password, is left out.  Naming a field which is not exposed in the required or
optional list throws ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input``.

Every name in the required and optional lists must be a field of the entity.
Associations cannot be input.  An unknown name, such as a typo, throws
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input``, which suggests the
closest exposed field when there is one::

  Field nmae is not a field of entity App\ORM\Entity\User. Did you mean "name"?


Input Type Names
================

Input type names are stable, so they do not change between builds and are
safe for schema diffs and client code generation.

* With no field lists the type is named after the entity type with ``_Input``
  appended.
* With field lists a short hash of the fields is appended as well, so each set
  of fields has its own name.  The order of the fields does not matter.

Calling ``input()`` again for the same entity and fields returns the same
type, so one input can be used by several mutations in a schema.

To choose the name yourself, pass it as the fourth parameter.  This is
recommended for inputs clients refer to:

.. code-block:: php

  $driver->input(Artist::class, ['name'], [], 'CreateArtistInput')

The name must be a valid GraphQL name, and a name can only be used for one
entity and set of fields.  Otherwise
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input`` is thrown.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
