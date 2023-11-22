GraphQL Data Types
==================

`webonyx/graphql-php <https://github.com/webonyx/graphql-php>`_
includes the basic GraphQL types.  These include

  * boolean
  * float
  * int
  * string

This library has many other types that are primarily
used to map Doctrine types to GraphQL types.  You may
use any of these types freely such as a blob for an
input type.  

To use a type you must fetch it from the TypeManager.

.. code-block:: php
   
   <?php
   
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
                         */`
                        
                        // ...save to doctrine blob column
                    },
                ],
            ],
        ]),
    ]);

Data types
----------

  * array
  * bigint
  * blob
  * boolean
  * date
  * date_immutable
  * datetime
  * datetime_immutable
  * datetimetz
  * datetimetz_immutable
  * decimal
  * float
  * guid
  * int
  * integer
  * json
  * simple_array
  * smallint
  * string
  * text
  * time
  * time_immutable
  * uuid


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
