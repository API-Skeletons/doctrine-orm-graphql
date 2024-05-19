Computed Fields
===============

You may add any computed field to an entity definition.  This is done with the
`EntityDefinition Event <events.html>`_.

Modify an Entity Definition
---------------------------

You may modify the array used to define an entity type before it is created.
This can be used for computed data.  You must attach a listener
before defining your GraphQL schema.

Events of this type are named ``Entity::class . '.definition'`` and the event
name cannot be modified.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
  use App\ORM\Entity\Artist;
  use App\ORM\Entity\Performance;
  use Doctrine\ORM\EntityManager;
  use GraphQL\Type\Definition\ResolveInfo;
  use League\Event\EventDispatcher;

  $driver = new Driver($entityManager);

  $driver->get(EventDispatcher::class)->subscribeTo(
      Artist::class . '.definition',
      static function (EntityDefinition $event) use ($driver): void {
          $definition = $event->getDefinition();

          // In order to modify the fields you must resolve the closure
          $fields = $definition['fields']();

          /**
           * Add a computed field to show the count of performances
           * This field will only be computed when it is requested specifically
           * in the query
           */
          $fields['performanceCount'] = [
              'type' => Type::int(),
              'description' => 'The count of performances for an Artist',
              'resolve' => static function (Artist $objectValue, array $args, $context, ResolveInfo $info) use ($driver): int {
                  $queryBuilder = $driver->get(EntityManager::class)->createQueryBuilder();
                  $queryBuilder
                     ->select('COUNT(performance)')
                     ->from(Performance::class, 'performance')
                     ->andWhere($queryBuilder->expr('performance.artist', ':artistId'))
                     ->setParameter('artistId', $objectValue->getId());

                  return $queryBuilder->getQuery()->getScalarResult();
              },
          ];

          // Assign modified fields array to the ArrayObject
          $definition['fields'] = $fields;
      }
  );

A query for this computed field:

.. code-block:: graphql

  query ArtistQueryWithComputedField($id: Int!)  {
    artist(id: $id) {
      id
      name
      performanceCount
    }
  }


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
