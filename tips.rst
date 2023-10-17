Tips and Tricks
===============

Here are tips for using this library in more edge-case ways.


Shared Type Manager 
-------------------

If you have more than one driver and it uses a different group and you use both drivers together in a single schema,
you will have type collisions with the Pagination and PageInfo types.  The reason a collision occurs is because the
GraphQL specification defines PageInfo as a `Reserved Type <https://relay.dev/graphql/connections.htm#sec-Reserved-Types>`_.

The problem is each driver will have its own definition for these types and they arne't identical (at runtime in PHP).
To work around this you must use a shared type manager:

.. code-block:: php
   
   <?php
   
   use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
   
   
   $driver1 = new Driver($entityManager, new Config(['group' => 'group1']));
   $driver2 = new Driver($entityManager, new Config(['group' => 'group2']));
   
   $driver2->set(TypeManager::class, $driver1->get(TypeManager::class));
   
.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
