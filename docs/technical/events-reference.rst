=================
Events Reference
=================

This document covers the PSR-14 compliant event system used for customizing query execution, type definitions, and metadata.

Overview
========

The library uses league/event v3.0 for PSR-14 compliant event dispatching. Events provide hooks for:

- Modifying QueryBuilder before query execution
- Customizing entity type definitions
- Altering metadata after extraction
- Adding custom logic at key points

All events implement ``League\Event\HasEventName`` interface.

Event Types
===========

QueryBuilder Event
------------------

**Class**: ``ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder``

**When Fired**: Before executing a database query (entities or collections)

**Purpose**: Modify the Doctrine QueryBuilder to add WHERE clauses, JOINs, or other query modifications

**Methods**:

.. code-block:: php

    class QueryBuilder implements HasEventName
    {
        public function eventName(): string;
        public function getQueryBuilder(): DoctrineQueryBuilder;
        public function getOffset(): int;
        public function getLimit(): int;
        
        // Resolver parameters
        public function getSource(): mixed;
        public function getArgs(): array;
        public function getContext(): mixed;
        public function getInfo(): ResolveInfo;
    }

**Example**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;
    
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilder $event) {
            // Add WHERE clause
            $event->getQueryBuilder()
                ->andWhere('entity.active = true')
                ->andWhere('entity.verified = true');
            
            // Access user context
            $userId = $event->getContext()['userId'] ?? null;
            if ($userId) {
                $event->getQueryBuilder()
                    ->andWhere('entity.userId = :userId')
                    ->setParameter('userId', $userId);
            }
        }
    );

**Usage in Schema**:

.. code-block:: php

    'resolve' => $driver->resolve(Artist::class, 'artist.query')

EntityDefinition Event
----------------------

**Class**: ``ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition``

**When Fired**: When an entity GraphQL ObjectType is created

**Purpose**: Modify the type definition to add/remove/modify fields

**Methods**:

.. code-block:: php

    class EntityDefinition implements HasEventName
    {
        public function eventName(): string;
        public function getDefinition(): Definition;
    }

**Definition Methods**:

.. code-block:: php

    class Definition extends ArrayObject
    {
        // Modify as an array
        $definition['fields'] = $modifiedFields;
        $definition['description'] = 'New description';
    }

**Example**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
    use GraphQL\Type\Definition\Type;
    
    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.definition',
        function (EntityDefinition $event) {
            $definition = $event->getDefinition();
            $fields = $definition['fields']();
            
            // Add computed field
            $fields['fullName'] = [
                'type' => Type::string(),
                'description' => 'Computed full name',
                'resolve' => fn($obj) => $obj->getFirstName() . ' ' . $obj->getLastName(),
            ];
            
            $definition['fields'] = $fields;
        }
    );

Metadata Event
--------------

**Class**: ``ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata``

**When Fired**: After metadata is built from attributes

**Purpose**: Programmatically modify metadata

**Methods**:

.. code-block:: php

    class Metadata implements HasEventName
    {
        public function eventName(): string;
        public function getMetadata(): \ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
    }

**Example**:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata;
    
    $driver->get(EventDispatcher::class)->subscribeTo(
        'metadata.build',
        function (Metadata $event) {
            $metadata = $event->getMetadata();
            
            // Modify entity limit
            $metadata[Artist::class]['limit'] = 50;
            
            // Add field description
            $metadata[Artist::class]['fields']['name']['description'] = 'Artist name';
        }
    );

Event Naming Patterns
=====================

Default Event Names
-------------------

**Entity Query**: ``EntityClass::class``

.. code-block:: php

    $driver->resolve(Artist::class);
    // Fires: Artist::class event

**Entity Definition**: ``EntityClass::class . '.definition'``

.. code-block:: php

    $driver->type(Artist::class);
    // Fires: Artist::class . '.definition' event

**Association Query**: Set via ``criteriaEventName`` attribute

.. code-block:: php

    #[GraphQL\Association(criteriaEventName: 'artist.performances')]
    private Collection $performances;
    // Fires: 'artist.performances' event

**Metadata**: ``'metadata.build'``

.. code-block:: php

    // Fires automatically after Driver construction

Custom Event Names
------------------

Specify custom event names for different contexts:

.. code-block:: php

    // Public API
    'artists' => $driver->completeConnection(
        Artist::class,
        'artist.public.definition',
        'artist.public.query'
    )
    
    // Admin API
    'artists' => $driver->completeConnection(
        Artist::class,
        'artist.admin.definition',
        'artist.admin.query'
    )

Common Patterns
===============

Security Filtering
------------------

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilder $event) {
            $user = $event->getContext()['user'];
            
            if (!$user->isAdmin()) {
                $event->getQueryBuilder()
                    ->andWhere('entity.public = true');
            }
        }
    );

Soft Delete Filtering
---------------------

.. code-block:: php

    #[GraphQL\Association(criteriaEventName: 'artist.performances')]
    private Collection $performances;
    
    // Event listener
    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.performances',
        function (QueryBuilder $event) {
            $event->getQueryBuilder()
                ->andWhere('entity.deletedAt IS NULL');
        }
    );

Adding Computed Fields
----------------------

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        Artist::class . '.definition',
        function (EntityDefinition $event) {
            $definition = $event->getDefinition();
            $fields = $definition['fields']();
            
            $fields['performanceCount'] = [
                'type' => Type::int(),
                'resolve' => function($artist, $args, $context, $info) {
                    return $artist->getPerformances()->count();
                },
            ];
            
            $definition['fields'] = $fields;
        }
    );

Eager Loading
-------------

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilder $event) {
            // Eager load performances to avoid N+1
            $event->getQueryBuilder()
                ->addSelect('performances')
                ->leftJoin('entity.performances', 'performances');
        }
    );

Summary
=======

Events provide powerful customization points:

- **QueryBuilder**: Modify queries before execution
- **EntityDefinition**: Customize type definitions
- **Metadata**: Programmatic metadata modifications

For more examples, see :doc:`advanced-topics`.
