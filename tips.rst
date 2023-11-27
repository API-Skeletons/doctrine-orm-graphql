Tips and Tricks
===============

Here are tips for using this library in more edge-case ways.

Filters for Scalar Queries
--------------------------

The ``$driver->filter(Entity::class)`` filter may be used outside of a
collection query.  For instance, to create a Doctrine query for the average
of a field you can construct your query like this:

.. code-block:: php
   
   <?php
   
   use ApiSkeletons\Doctrine\ORM\GraphQL\Criteria\Filters;
   use ApiSkeletons\Doctrine\QueryBuilder\Filter\Applicator;
   use Doctrine\ORM\EntityManager;
   use GraphQL\Type\Definition\Type;

   'average' => [
       'type' => Type::float(),
       'args' => [
           'filter' => $driver->filter(Entity::class),
       ],
       'resolve' => function ($root, array $args, $context, ResolveInfo $info) use ($driver) {
           $entityManager = $driver->get(EntityManager::class);
           $filters = new Filters();
           $applicator = new Applicator($entityManager, Entity::class)
               ->setEntityAlias('entity');
 
           $queryBuilder = $applicator($filters->buildQueryArray($args['filter'] ?? []))
               ->select('AVG(entity.fieldName)');

           return $queryBuilder->getQuery()->getScalarResult();
       }
   ],


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
