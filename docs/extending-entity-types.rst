======================
Extending Entity Types
======================

There are two ways to extend an entity type.  First, you can extend an entity
by listening to the ``EntityDefinition`` event.  Second, you can extend an entity
by creating a new entity type that extends the existing entity type.


Extending An Entity Globally
============================

The ``EntityDefinition`` event is dispatched when an entity type is created.
Modifying the definition will affect all entity types for that entity.


Extending An Entity into a New Type
===================================

The ``$driver->type()`` method takes an optional event name parameter.
When it is called with an event name, the event will be dispatched when the
entity type is created and the type name in GraphQL will be the entity name
with the event name appended.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst

