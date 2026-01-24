================
Types Reference
================

This document covers the type system, including built-in types, custom types, and type registration.

Built-in Types
==============

Scalar Types
------------

The library provides GraphQL types for all Doctrine types:

**DateTime Types**:

- ``DateTime`` - ISO-8601 format (e.g., 2004-02-12T15:19:21+00:00)
- ``DateTimeTZ`` - DateTime with timezone
- ``DateTimeImmutable`` - Immutable DateTime
- ``DateTimeTZImmutable`` - Immutable DateTime with timezone
- ``Date`` - Date only (YYYY-MM-DD)
- ``Time`` - Time only (HH:MM:SS)
- ``TimeImmutable`` - Immutable Time

**Binary Types**:

- ``Blob`` - Binary data (base64 encoded in GraphQL)

**JSON Types**:

- ``Json`` - JSON data (object or array)

Type Mapping
------------

Doctrine to GraphQL type mapping:

===============  ===================
Doctrine Type    GraphQL Type
===============  ===================
integer          Int
bigint           Int
smallint         Int
string           String
text             String
boolean          Boolean
decimal          Float
float            Float
datetime         DateTime
date             Date
time             Time
json             Json
blob             Blob
===============  ===================

Custom Scalar Types
===================

Creating Custom Types
---------------------

Extend ``GraphQL\Type\Definition\ScalarType``:

.. code-block:: php

    use GraphQL\Type\Definition\ScalarType;
    use GraphQL\Language\AST\StringValueNode;
    
    class EmailType extends ScalarType
    {
        public string $description = 'Email address';
        
        public function serialize($value): string
        {
            return (string) $value;
        }
        
        public function parseValue($value): string
        {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email');
            }
            return $value;
        }
        
        public function parseLiteral($valueNode, ?array $variables = null): string
        {
            if (!$valueNode instanceof StringValueNode) {
                throw new \Exception('Expected string');
            }
            return $this->parseValue($valueNode->value);
        }
    }

Register in TypeContainer:

.. code-block:: php

    $driver->get(TypeContainer::class)->set('email', new EmailType());

Use in Entity:

.. code-block:: php

    #[GraphQL\Field(type: 'email')]
    private string $contactEmail;

Entity Types
============

ObjectType Generation
---------------------

Entity types are generated automatically from metadata:

.. code-block:: php

    #[GraphQL\Entity(description: 'Artists')]
    class Artist
    {
        #[GraphQL\Field]
        private int $id;
        
        #[GraphQL\Field]
        private string $name;
    }

Generates:

.. code-block:: graphql

    type Artist_default {
        id: Int!
        name: String!
    }

Connection Types
================

Connection Model
----------------

Collections use the GraphQL Connection pattern:

.. code-block:: graphql

    type ArtistConnection {
        edges: [ArtistEdge]
        totalCount: Int!
        pageInfo: PageInfo!
    }
    
    type ArtistEdge {
        cursor: String!
        node: Artist!
    }
    
    type PageInfo {
        startCursor: String
        endCursor: String
        hasNextPage: Boolean!
        hasPreviousPage: Boolean!
    }

Input Types
===========

InputObjectType for Mutations
------------------------------

Created via ``$driver->input()``:

.. code-block:: php

    $driver->input(
        Artist::class,
        ['name'],           // Required fields
        ['description']     // Optional fields
    );

Generates:

.. code-block:: graphql

    input Artist_Input {
        name: String!
        description: String
    }

Type Container
==============

Registration
------------

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
    
    $typeContainer = $driver->get(TypeContainer::class);
    
    // Register custom type
    $typeContainer->set('email', new EmailType());
    $typeContainer->set('url', new URLType());

Retrieval
---------

.. code-block:: php

    $emailType = $driver->type('email');

For complete documentation, see :doc:`driver-reference` and :doc:`architecture`.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
