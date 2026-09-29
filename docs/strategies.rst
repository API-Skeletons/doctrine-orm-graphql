===================
Hydrator Strategies
===================

Some hydrator strategies are supplied with this library.  You may also add your own hydrator
strategies.

Included strategies are in the namespace ``ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy``

FieldDefault
============

This strategy is applied to most field values.  It will return the exact value of the field.


ToInteger
=========

This strategy will convert the field value to an integer to be handled as an integer internal to PHP.


ToFloat
=======

Similar to ``ToInteger``, this will convert the field value to a float to be handled as a float internal to PHP.


ToBoolean
=========

Similar to ``ToInteger``, this will convert the field value to a boolean to be handled as a boolean internal to PHP.


ToString
========

Similar to ``ToInteger``, this will convert the field value to a string to be handled as a string internal to PHP.
Scalars and ``Stringable`` objects are converted; ``null`` is returned as ``null``.  This strategy is applied by
default to ``decimal`` fields.  To use it for another field, set it as the ``hydratorStrategy`` and set the GraphQL
``type`` of the field to ``string``.

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;

    #[GraphQL\Field(type: 'string', hydratorStrategy: ToString::class)]
    #[ORM\Column(type: "integer")]
    private int $zipCode;


Add a custom hydrator strategy
==============================

To add a custom hydrator strategy, create a class that implements the interface
``ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\Strategy``.  Add the class to the
hydrator strategy container after creating the driver.

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
    use App\GraphQL\Hydrator\Strategy\S3Url;

    $driver = new Driver($entityManager);

    $driver->get(HydratorContainer::class)
        ->set(S3Url::class, static fn () => new S3Url());

The S3Url class would look something like this:

.. code-block:: php

    namespace App\GraphQL\Hydrator\Strategy;

    use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\Strategy;
    use Illuminate\Support\Facades\Storage;

    /**
     * Resolve the token to an S3 url
     */
    class S3Url implements
        Strategy
    {
        public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
        {
            if (! $value) {
                return $value;
            }

            return Storage::disk('s3')->url($value);
        }

        /**
         * This library does not hydrate using the hydrator but this method is required
         * @param mixed[]|null $data
         */
        public function hydrate(mixed $value, array|null $data): mixed
        {
            return $value;
        }
    }

Then add the hydratorStrategy to the entity field you wish to custom extract.

.. code-block:: php

    #[GraphQL\Field(hydratorStrategy: S3Url::class)]
    #[ORM\Column(type: "text", nullable: true)]
    public $favicon;

The field name
==============

A strategy is a shared instance.  The hydrator strategy container creates one instance of
each strategy class and that instance is used for every field, on every entity, which names
it in ``hydratorStrategy``.  The Laminas hydrator does not tell a strategy which field it is
extracting, so this library passes the field name as the third argument, ``$fieldName``, to
``extract()``.  This allows one strategy to act differently per field without keeping
per-field state.

A strategy for a collection-valued association is the exception.  It implements Doctrine's
``CollectionStrategyInterface``, and the Doctrine hydrator sets the collection name and class
metadata on it, so each collection association is given its own clone of the strategy.

.. code-block:: php

    public function extract(mixed $value, object|null $object = null, string|null $fieldName = null): mixed
    {
        return match ($fieldName) {
            'thumbnail' => Storage::disk('s3')->url('thumbnails/' . $value),
            default     => Storage::disk('s3')->url($value),
        };
    }

``$fieldName`` is the Doctrine field name on the entity, not the GraphQL field name.  If a
field has an ``alias``, the strategy still receives the Doctrine field name.

`Computed fields <computed-fields.html>`_ do not use hydrator strategies.

A strategy which implements only ``Laminas\Hydrator\Strategy\StrategyInterface`` is still
supported.  It is called without the field name.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
