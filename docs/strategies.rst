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


Add a custom hydrator strategy
==============================

To add a custom hydrator strategy, create a class that implements the interface
``Laminas\Hydrator\Strategy\StrategyInterface``.  Add the class to the
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

    use Illuminate\Support\Facades\Storage;
    use Laminas\Hydrator\Strategy\StrategyInterface;

    /**
     * Resolve the token to an S3 url
     */
    class S3Url implements
        StrategyInterface
    {
        public function extract(mixed $value, object|null $object = null): mixed
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

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
