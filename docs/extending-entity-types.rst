======================
Extending Entity Types
======================

There are two ways to extend an entity type.  You can extend an entity
by listening to the ``EntityDefinition`` event.  You can extend an entity
by creating a new entity type by using a custom event name to replace the default.


Extending An Entity Globally
============================

The ``EntityDefinition`` event is dispatched when an entity type is created.
The event name is ``Entity::class . '.definition'``.  All entity types for
``Entity::class`` will be affected by this event.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
  use App\ORM\Entity\Artist;
  use GraphQL\Type\Definition\ResolveInfo;
  use League\Event\EventDispatcher;

  $driver = new Driver($entityManager);

  $driver->get(EventDispatcher::class)->subscribeTo(
      Artist::class . '.definition',
      static function (EntityDefinition $event): void {
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
      },
  );

  $graphQLType = $driver->type(Artist::class);


Extending An Entity into a New Type
===================================

The ``$driver->type()`` method takes an optional event name parameter.
When it is called with an event name, the event will be replace the default
``Entity::class . '.definition'`` dispatched when the
entity type is created and the type name in GraphQL will be the entity name
with the event name appended.

.. code-block:: php

  $newType = $driver->type(Artist::class, 'eventName');


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
