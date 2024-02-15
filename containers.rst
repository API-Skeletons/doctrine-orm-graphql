Containers
==========

Internal to the classes used in this library, PSR-11 containers are used.
You can set values in the containers using ``container->set($id, $value);``.
If a value already exists for the ``$id`` then it will be overwritten.

Containers will execute any ``Closure`` found when getting from itself and pass
the container to the closure as the only argument.  This provides a basic
method for using factories.  Once a factory has executed the result will
replace the factory so later requests will just get the composed object.

There are two containers you should be aware of if you intened to extend this
library.

Type Manager
------------

The ``TypeManager`` stores all the GraphQL types created or
used in the library.  If you want to specify your own type for a field you'll
need to add your custom type to the container.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
    use GraphQL\Type\Definition\Type;

    $driver = new Driver($this->getEntityManager());
    $driver->get(TypeManager::class)
        ->set('customtype', fn() => Type::string());


Custom Types
------------

For instance, if your schema has a ``timestamp`` type, that data type is not suppored
by default in this library.  But adding the type is just a matter of creating a
new Timestamp type (modifying the DateTime class is uncomplicated) then adding the
type to the type manager.

  .. code-block:: php

    $driver->get(TypeManager::class)
        ->set('timestamp', fn() => new Type\Timestamp());


Hydrator Factory
----------------

The ``HydratorFactory`` stores hydrator strategies,
filter classes, naming strategy classes, and all the generated hydrators.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorFactory;

    $driver = new Driver($this->getEntityManager());
    $driver->get(HydratorFactory::class)
        ->set('customstrategy', fn() => new CustomStrategy());


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
