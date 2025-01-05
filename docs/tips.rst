===============
Tips and Tricks
===============

Here are tips for using this library in more edge-case ways.


Serve a CSV Field as a GraphQL Array
====================================

If you have a field in your entity that is a CSV string and you want to
convert it to a GraphQL array, you can use a custom hydrator strategy and custom type.

Create a new hydrator strategy

.. code-block:: php

   namespace App\GraphQL\Hydrator\Strategy;

   use Laminas\Hydrator\Strategy\StrategyInterface;

   use function explode;
   use function implode;

   class CsvString implements
       StrategyInterface
   {
       /** @return String[] */
       public function extract(mixed $value, object|null $object = null): array
       {
           if (! $value) {
               return [];
           }

           return explode(',', (string) $value);
       }

       /**
        * StrategyInterface requires a hydrate method but this library does not
        * perform hydration of data; just extraction.
        *
        * @param mixed[]|null $data
        */
       public function hydrate(mixed $value, array|null $data = null): mixed
       {
           if (! $value) {
             return ;
           }

           return implode(',', $value);
       }
   }

Add the type and hydrator strategy to the field:

.. code-block:: php

   use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
   use App\GraphQL\Hydrator\Strategy\CsvString;

   #[GraphQL\Field(type: 'csvstring', hydratorStrategy: CsvString::class)]
   public string $csvField;

Add the new type and hydrator strategy to the Driver:

.. code-block:: php
   use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
   use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
   use App\GraphQL\Hydrator\Strategy\CsvString;

   $driver->get(HydratorContainer::class)->set(CsvString::class, fn() => new CsvString());
   $driver->get(TypeContainer::class)->set('csvstring', fn() => Type::listOf(Type::string()));


Filters for Scalar Queries
==========================

The ``$driver->filter(Entity::class)`` filter may be used outside of a
connection.  For instance, to create a Doctrine query for the average
of a field you can construct your query like this:

.. code-block:: php

   use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as FilterQueryBuilder;
   use ApiSkeletons\Doctrine\ORM\GraphQL\Types\Entity\EntityTypeContainer;
   use Doctrine\ORM\EntityManager;
   use GraphQL\Type\Definition\Type;

   'average' => [
       'type' => Type::float(),
       'args' => [
           'filter' => $driver->filter(Entity::class),
       ],
       'resolve' => function ($root, array $args, $context, ResolveInfo $info) use ($driver) {
           $entity = $driver->get(EntityTypeContainer::class)->get(Entity::class)

           $filterQueryBuilder = new FilterQueryBuilder();

           $queryBuilder = $driver->get(EntityManager::class)
               ->createQueryBuilder();
           $queryBuilder
               ->select('AVG(entity.fieldName)')
               ->from(Entity::class, 'entity');

           // The apply method requires a third parameter of the entity
           $filterQueryBuilder->apply($args['filter'], $queryBuilder, $entity);

           return $queryBuilder->getQuery()->getScalarResult();
       }
   ],


Shared Type Container
=====================

If you have more than one driver and it uses a different group,
and you use both drivers together in a single schema,
you will have type collisions with the Pagination and PageInfo types.
The reason a collision occurs is because the
GraphQL specification defines PageInfo as a `Reserved Type <https://relay.dev/graphql/connections.htm#sec-Reserved-Types>`_.

The problem is each driver will have its own definition for
these types and they are not identical at runtime in PHP.
To work around this you must use a shared type container:

.. code-block:: php

   use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;

   $driver1 = new Driver($entityManager, new Config(['group' => 'group1']));
   $driver2 = new Driver($entityManager, new Config(['group' => 'group2']));

   $driver2->set(TypeContainer::class, $driver1->get(TypeContainer::class));

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
