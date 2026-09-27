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
``InputObjectType`` with all the fields set to nonNull, thereby making them
required.  Since this is rarely what is intended, there are two optional
parameters to specify required and optional fields.

.. code-block:: php

  $driver->input(Entity::class, ['requiredField'], ['optionalField'])

In the above mutation example the ``name`` field is required and there are no
optional fields, so the only field in the ``input`` args will be ``name``.
The ``name`` input field will be typed according to its metadata configuration.

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

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
