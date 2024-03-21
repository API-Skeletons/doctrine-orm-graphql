================
The Driver class
================

The Driver class is the gateway to much of the funcitonality of this library.
It has many top-level functions detailed here.


connection(), pagination(), and resolve()
=========================================

The ``connection`` function returns a wrapper for an entity type.  This wrapper,
in combination with the ``resolve`` and ``pagination`` functions, implements the
`GraphQL Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

    $driver = new Driver($this->getEntityManager());

    $schema = new Schema([
        'query' => new ObjectType([
            'name' => 'query',
            'fields' => [
                'artists' => [
                    'type' => $driver->connection(Artist::class),
                    'args' => [
                        'pagination' => $driver->pagination(),
                    ],
                    'resolve' => $driver->resolve(Artist::class),
                ],
            ],
        ]),
    ]);


filter()
========

Based on the attribute configuration of an entity, this funciton adds a
``filter`` argument to a connection.  See `filters <queries>`_ for a list of
available filters per field.  The args field must be ``filter``.

Filters are applied to a ``connection``.  It is also possible to use them ad-hoc
as detailed in `tips <tips>`_.

  .. code-block:: php
    'args' => [
        'pagination' => $driver->pagination(),
        'filter' => $driver->filter(Artist::class),
    ],


input()
=======

This function creates an InputObjectType for the given entity.  There are three
parameters:  The entity class name, an array of required fields, and an array
of optional fields.


type()
======

This function returns GraphQL types for all Doctrine types, any custom types,
and Doctrine entity types.

There are two type managers:  ``TypeManager`` and ``EntityTypeManager``.
Types from each of these managers are returned from this `type()` function.

See `types <types>`_ for details on custom types and using the ``TypeManager``.

The ``EntityTypeManager`` is used only for Doctrine entities and is populated
though the `metadata <metadata>`_.  This class is used internally for generating
``ObjectType``s for entities.

Though a ``connection`` is a type, it is not
available through this function.  Use the ``connection`` function of the Driver.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
