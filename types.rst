Data Types
==========

`webonyx/graphql-php <https://github.com/webonyx/graphql-php>`_
includes the basic GraphQL types.

This library has many other types that are primarily
used to map Doctrine types to GraphQL types.

Data Type Mappings
------------------

.. list-table:: Data Type Mappings
   :widths: 33 33 34
   :header-rows: 1

   * - GraphQL and Doctrine
     - PHP
     - Javascript
   * - array
     - array of strings
     - array of strings
   * - bigint
     - string
     - integer or string
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
   * - datetime
     - DateTime
     - ISO 8601 date string
   * - datetime_immutable
     - DateTimeImmutable
     - ISO 8601 date string
   * - datetimetz
     - DateTime
     - ISO 8601 date string
   * - datetimetz_immutable
     - DateTimeImmutable
     - ISO 8601 date string
   * - decimal
     - string
     - float
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
     - string
     - string of json
   * - simple_array
     - array of strings
     - array of strings
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
   * - uuid
     - Ramsey\\Uuid\\UuidInterface
     - string

See also `Doctrine Mapping Types <https://www.doctrine-project.org/projects/doctrine-orm/en/2.16/reference/basic-mapping.html#doctrine-mapping-types>`_.

Using Types
-----------

You may use any of the above types freely such as a blob for an
input type.

To use a type you must fetch it from the TypeManager.

.. code-block:: php

   use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;

   $graphqlBlobType = $driver->get(TypeManager::class)
       ->get('blob');

    $schema = new Schema([
        'mutation' => new ObjectType([
            'name' => 'mutation',
            'fields' => [
                'uploadFile' => [
                    'type' => $driver->type(ArtistFile::class),
                    'args' => [
                        'file' => $graphqlBlobType,
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
------------

If your schema has a ``timestamp`` type, that data type is not suppored
by this library.  But adding the type is just a matter of creating a
new Timestamp type extending ``GraphQL\Type\Definition\ScalarType`` then adding
the type to the type manager.

  .. code-block:: php

     $driver->get(TypeManager::class)
         ->set('timestamp', fn() => new Timestamp());


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
