==========
Data Types
==========

`webonyx/graphql-php <https://github.com/webonyx/graphql-php>`_
includes the basic GraphQL types.

This library has many other types that are primarily
used to map Doctrine types to GraphQL types.

Data Type Mappings
==================

.. list-table:: Data Type Mappings
   :widths: 33 33 34
   :header-rows: 1

   * - Doctrine (GraphQL type if another)
     - PHP
     - Javascript
   * - ascii_string
     - string
     - string
   * - bigint
     - string
     - integer or string
   * - binary (as Blob)
     - string (binary)
     - Base64 encoded string
   * - blob
     - string (binary)
     - Base64 encoded string
   * - boolean
     - boolean
     - boolean
   * - date
     - DateTime
     - string as Y-m-d
   * - date_immutable
     - DateTimeImmutable
     - string as Y-m-d
   * - dateinterval
     - DateInterval
     - ISO 8601 duration string, e.g. P1DT2H or -P1D
   * - datetime
     - DateTime
     - ISO 8601 date string
   * - datetime_immutable
     - DateTimeImmutable
     - ISO 8601 date string
   * - datetime_utc (as datetime)
     - DateTime
     - ISO 8601 date string in UTC
   * - datetime_utc_immutable (as datetime_immutable)
     - DateTimeImmutable
     - ISO 8601 date string in UTC
   * - datetimetz
     - DateTime
     - ISO 8601 date string
   * - datetimetz_immutable
     - DateTimeImmutable
     - ISO 8601 date string
   * - decimal
     - string
     - string, which keeps its precision
   * - enum
     - string
     - string
   * - float
     - float
     - float
   * - guid
     - string
     - string
   * - int & integer
     - integer
     - integer
   * - json
     - array
     - string of json
   * - json_object, jsonb and jsonb_object (as json)
     - stdClass or array
     - string of json
   * - number
     - ``BcMath\Number``
     - string
   * - simple_array
     - array of strings
     - array of strings
   * - smallfloat
     - float
     - float
   * - smallint
     - integer
     - integer
   * - string
     - string
     - string
   * - text
     - string
     - string
   * - time
     - DateTime
     - string as H:i:s or H:i:s.u
   * - time_immutable
     - DateTimeImmutable
     - string as H:i:s or H:i:s.u

``datetime_utc``, ``datetime_utc_immutable``, ``enum``, ``json_object``,
``jsonb``, ``jsonb_object``, ``number`` and ``smallfloat`` are types of DBAL 4.

A ``decimal`` or ``number`` is a string in GraphQL, which keeps its precision.  An input or
filter value of it is also a string; convert a ``number`` value to a
``BcMath\Number`` before setting it on an entity.

A ``dateinterval`` is stored as a string, which does not order as the duration
does, so it has only the ``eq``, ``neq``, ``in``, ``notin`` and ``isnull``
filters.

A field mapped with an ``enumType`` is represented by the value of the enum,
the value stored in the database, as the field's type.  An input or filter
value of it is also the value; use ``Enum::from()`` to convert it before
setting it on an entity.  A custom hydrator strategy is given the enum case.

See also `Doctrine Mapping Types <https://www.doctrine-project.org/projects/doctrine-orm/en/2.16/reference/basic-mapping.html#doctrine-mapping-types>`_.

Using Types
===========

You may use any of the above types freely such as a blob for an
input type.

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;

    $schema = new Schema([
        'mutation' => new ObjectType([
            'name' => 'mutation',
            'fields' => [
                'uploadFile' => [
                    'type' => $driver->type(ArtistFile::class),
                    'args' => [
                        'file' => $driver->type('blob'),
                    ],
                    'resolve' => function ($root, array $args, $context, ResolveInfo $info) use ($driver) {
                        /**
                         * $args['file'] will be sent base64 encoded then
                         * unencoded in the PHP type so by the time it gets
                         * here it is already an uploaded file
                         */

                        // ...save to doctrine blob column
                    },
                ],
            ],
        ]),
    ]);


Custom Types
============

If your schema has a ``timestamp`` type, that data type is not supported
by this library.  But adding the type is just a matter of creating a
new Timestamp type extending ``GraphQL\Type\Definition\ScalarType`` then adding
the type to the type container.

  .. code-block:: php

     $driver->get(TypeContainer::class)
         ->set('timestamp', fn() => new Timestamp());

See also `Serve a CSV Field as a GraphQL Array <tips.html#serve-a-csv-field-as-a-graphql-array>`_.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
