==========
Containers
==========

Internal to the classes used in this library, PSR-11 containers are used.
You can set values in the containers using ``container->set($id, $value);``.
**If a value already exists for the ``$id`` it will be overwritten.**

Containers will execute any ``Closure`` found when getting from itself and pass
the container to the closure as the only argument.  This provides a basic
method for factories.  Once a factory has executed, the result will
replace the factory so later requests will just get the composed object.

There are two containers you should be aware of if you intened to extend this
library.

Type Container
==============

The ``TypeContainer`` stores all the GraphQL types created or
used in the library.  If you want to specify your own type for a field you'll
need to add your custom type to the container.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
    use GraphQL\Type\Definition\Type;

    $driver = new Driver($this->getEntityManager());
    $driver->get(TypeContainer::class)
        ->set('customtype', fn() => Type::string());


Custom Types
============

For instance, if your schema has a ``timestamp`` type, that data type is not suppored
by default in this library.  But adding the type is just a matter of creating a
new Timestamp type (modifying the DateTime class is uncomplicated) then adding the
type to the type manager.

  .. code-block:: php

    $driver->get(TypeContainer::class)
        ->set('timestamp', fn() => new Type\Timestamp());


Hydrator Container
==================

The ``HydratorContainer`` stores hydrator strategies and all the generated hydrators.
Custom HydratorStrategies can be added to the container.

  .. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
    use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;

    $driver = new Driver($this->getEntityManager());
    $driver->get(HydratorContainer::class)
        ->set('customstrategy', fn() => new CustomStrategy());


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
