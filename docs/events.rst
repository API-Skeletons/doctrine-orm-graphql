======
Events
======

Query Builder Event
===================

Each top level ``connection`` uses a Doctrine QueryBuilder object.  This QueryBuilder
object may be modified to filter the data for the logged in user and such.
For instance, this can be used as a security layer and can be used to make
customizations to QueryBuilder objects.  QueryBuilders are built then
triggered through an event.  Listen to this event and modify the passed
QueryBuilder to apply your security.

Event names are passed as a second parameter to a ``$driver->resolve()``.

You may specify an event name to resolve a ``connection``.  Only this event will
fire when the QueryBuilder is created.

In the code below, the custom event ``Artist::class . '.queryBuilder'`` will fire:

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use App\ORM\Entity\Artist;
  use GraphQL\Type\Definition\ObjectType;
  use GraphQL\Type\Schema;

  $schema = new Schema([
    'query' => new ObjectType([
        'name' => 'query',
        'fields' => [
            'artists' => [
                'type' => $driver->connection(Artist::class),
                'args' => [
                    'filter' => $driver->filter(Artist::class),
                ],
                'resolve' => $driver->resolve(Artist::class, Artist::class . '.queryBuilder'),
            ],
        ],
    ]),
  ]);

To listen for this event and add filtering, such as filtering for the context
user, create a listener.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;
  use League\Event\Event;
  use League\Event\EventDispatcher;

  $driver->get(Emitter::class)->addListener(Artist::class . '.queryBuilder',
      function(Event $leagueEvent, QueryBuilder $event) {
          $event->getQueryBuilder()
              ->innerJoin('entity.user', 'user') // The default entity alias is always `entity`
              ->andWhere($event->getQueryBuilder()->expr()->eq('user.id', ':userId'))
              ->setParameter('userId', $event->getContext()['user']->getId())
              ;
      }
  );

The ``QueryBuilder`` event has one function in addition to getters for
all resolve parameters:

* ``getQueryBuilder`` - Will return a query builder with the user specified
  filters already applied.


Criteria Event
==============

When an association is resolved from an entity or another association, you may
listen to the Criteria Event to add additional criteria for filtering
the association if you assigned an event name in the attributes.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria;
  use App\ORM\Entity\Artist;
  use League\Event\Event;
  use League\Event\EventDispatcher;

  #[GraphQL\Entity]
  class Artist
  {
      #[GraphQL\Field]
      public $id;

      #[GraphQL\Field]
      public $name;

      #[GraphQL\Association(filterCriteriaEventName: self::class . '.performances.criteria')]
      public $performances;
  }

  // Add a listener to your driver
  $driver->get(Emitter::class)->addListener(
      Artist::class . '.performances.criteria',
      function (Event $leagueEvent, Criteria $event): void {
          $event->getCriteria()->andWhere(
              $event->getCriteria()->expr()->eq('isDeleted', false)
          );
      },
  );

The ``Criteria`` event has one function in addition to getters for
all resolve parameters:

* ``getCriteria`` - Will return a Criteria object with the user specified
  filters already applied.


Modify an Entity Definition
===========================

You may modify the array used to define an entity type before it is created.
This can be used for generated data and the like.  You must attach to events
before defining your GraphQL schema.

Events of this type are named ``Entity::class . '.definition'`` and the event
name cannot be modified.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
  use App\ORM\Entity\Artist;
  use GraphQL\Type\Definition\ResolveInfo;
  use League\Event\Event;
  use League\Event\EventDispatcher;

  $driver = new Driver($entityManager);

  $driver->get(Emitter::class)->addListener(
      Artist::class . '.definition',
      static function (Event $leagueEvent, EntityDefinition $event): void {
          $definition = $event->getDefinition();

          // In order to modify the fields you must resolve the closure
          $fields = $definition['fields']();

          // Add a custom field to show the name without a prefix of 'The'
          $fields['nameUnprefix'] = [
              'type' => Type::string(),
              'description' => 'A computed dynamically added field',
              'resolve' => static function ($objectValue, array $args, $context, ResolveInfo $info): mixed {
                  return trim(str_replace('The', '', $objectValue->getName()));
              },
          ];

          $definition['fields'] = $fields;
      }
  );

The ``EntityDefinition`` event has one function:

* ``getDefinition`` - Will return an ArrayObject with the ObjectType definition.
  Because this is an ArrayObject you may manipulate it as
  needed and the value is set by reference, just like the
  QueryBuilder event above.

A clever use of this event is to add a new field for related data and specify
a custom QueryBuilder event in the ``$driver->resolve()`` function.


Manually change the Metadata
============================

You may modify the metadata directly when built.  This event must be subscribed
to immediately after creating the driver. See `Metadata documentation <metadata.html>`_.

This event is named ``'metadata.build'``.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata;
  use App\ORM\Entity\Performance;
  use League\Event\Event;
  use League\Event\EventDispatcher;

  $driver = new Driver($entityManager);

  $driver->get(Emitter::class)->addListener(
      'metadata.build',
      static function (Event $leagueEvent, Metadata $event): void {
          $metadata = $event->getMetadata();

          $metadata[Performance::class]['limit'] = 100;
      },
  );

The ``BuildMetadata`` event has one function:

* ``getMetadata`` - Will return an ArrayObject with the metadata.
  Because this is an ArrayObject you may manipulate it as
  needed and the value is set by reference, just like the
  QueryBuilder event above.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
