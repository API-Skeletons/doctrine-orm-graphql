<p align="center">
    <img src="https://placehold.co/10x10/337ab7/337ab7.png" width="100%" height="15px">
    <img src="https://raw.githubusercontent.com/API-Skeletons/doctrine-orm-graphql/master/banner.png" width="450px">
</p>

GraphQL Type Driver for Doctrine ORM
====================================

[![Build Status](https://github.com/API-Skeletons/doctrine-orm-graphql/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/API-Skeletons/doctrine-orm-graphql/actions/workflows/continuous-integration.yml?query=branch%3Amain)
[![Code Coverage](https://codecov.io/gh/API-Skeletons/doctrine-orm-graphql/branch/main/graphs/badge.svg)](https://codecov.io/gh/API-Skeletons/doctrine-orm-graphql/branch/main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/API-Skeletons/doctrine-orm-graphql/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/API-Skeletons/doctrine-orm-graphql/?branch=main)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2b-blue)](https://img.shields.io/badge/PHP-8.1%2b-blue)
[![Total Downloads](https://poser.pugx.org/api-skeletons/doctrine-orm-graphql/downloads)](//packagist.org/packages/api-skeletons/doctrine-orm-graphql)
[![License](https://poser.pugx.org/api-skeletons/doctrine-orm-graphql/license)](//packagist.org/packages/api-skeletons/doctrine-orm-graphql)

* 2023-12-12 - New version 9.0 is released dropping support for PHP 8.0

This library provides a GraphQL driver for Doctrine ORM for use with [webonyx/graphql-php](https://github.com/webonyx/graphql-php).
Configuration is available from simple to verbose.  Multiple configurations for multiple drivers are supported.

This library **does not** try to redefine how the excellent library [webonyx/graphql-php](https://github.com/webonyx/graphql-php) operates.  Instead, it creates types to be used within the framework that library provides.

Please read the [detailed documentation](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/).

For an example implementation post to `https://graphql.lcdb.org/`

For an example application see [https://github.com/lcdborg/graphql.lcdb.org](https://github.com/lcdborg/graphql.lcdb.org)

Library Highlights
------------------

* [PHP 8 Attributes](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/attributes.html) for configuration
* [Multiple configuration group support](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/config.html)
* Supports all [Doctrine Types](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/types.html#data-type-mappings) and allows custom types
* Pagination with the [GraphQL Complete Connection Model](https://graphql.org/learn/pagination/#complete-connection-model)
* Supports [filtering of sub-collections](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/queries.html)
* [Events](https://github.com/API-Skeletons/doctrine-orm-graphql#events) for modifying queries, entity types and more
* Uses the [Doctrine Laminas Hydrator](https://www.doctrine-project.org/projects/doctrine-laminas-hydrator/en/3.1/index.html) for extraction by value or by reference
* Conforms to the [Doctrine Coding Standard](https://www.doctrine-project.org/projects/doctrine-coding-standard/en/9.0/index.html)

Installation
------------

Run the following to install this library using [Composer](https://getcomposer.org/):

```bash
composer require api-skeletons/doctrine-orm-graphql
```

Quick Start
-----------

Add attributes to your Doctrine entities or see [globalEnable](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/config.html#globalenable) for all entities in your schema without attribute configuration.

```php
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;

#[GraphQL\Entity]
class Artist
{
    #[GraphQL\Field]
    public $id;

    #[GraphQL\Field]
    public $name;

    #[GraphQL\Association]
    public $performances;
}

#[GraphQL\Entity]
class Performance
{
    #[GraphQL\Field]
    public $id;

    #[GraphQL\Field]
    public $venue;

    /**
     * Not all fields need attributes.
     * Only add attribues to fields you want available in GraphQL
     */
    public $city;
}
```

Create the driver and GraphQL schema

```php
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use Doctrine\ORM\EntityManager;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

$driver = new Driver($entityManager);

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'query',
        'fields' => [
            'artists' => [
                'type' => $driver->connection($driver->type(Artist::class)),
                'args' => [
                    'filter' => $driver->filter(Artist::class),
                    'pagination' => $driver->pagination(),
                ],
                'resolve' => $driver->resolve(Artist::class),
            ],
        ],
    ]),
    'mutation' => new ObjectType([
        'name' => 'mutation',
        'fields' => [
            'artistUpdateName' => [
                'type' => $driver->type(Artist::class),
                'args' => [
                    'id' => Type::nonNull(Type::id()),
                    'input' => Type::nonNull($driver->input(Artist::class, ['name'])),
                ],
                'resolve' => function ($root, $args) use ($driver): Artist {
                    $artist = $driver->get(EntityManager::class)
                        ->getRepository(Artist::class)
                        ->find($args['id']);

                    $artist->setName($args['input']['name']);
                    $driver->get(EntityManager::class)->flush();

                    return $artist;
                },
            ],
        ],
    ]),
]);
```

Run GraphQL queries

```php
use GraphQL\GraphQL;

$query = '{
  artists {
    edges {
      node {
        id
        name
        performances {
          edges {
            node {
              venue
            }
          }
        }
      }
    }
  }
}';

$result = GraphQL::executeQuery(
    schema: $schema,
    source: $query,
    variableValues: null,
    operationName: null
);

$output = $result->toArray();
```

Run GraphQL mutations

```php
use GraphQL\GraphQL;

$query = '
  mutation ArtistUpdateName($id: Int!, $name: String!) {
    artistUpdateName(id: $id, input: { name: $name }) {
      id
      name
    }
  }
';

$result = GraphQL::executeQuery(
    schema: $schema,
    source: $query,
    variableValues: [
        'id' => 1,
        'name' => 'newName',
    ],
    operationName: 'ArtistUpdateName'
);

$output = $result->toArray();
```

Filtering
---------

For every enabled field and association, filters are available for querying.

Example

```gql
{
  artists ( 
    filter: { 
      name: { 
        contains: "dead" 
      } 
    } 
  ) {
    edges {
      node {
        id
        name
        performances ( 
          filter: { 
            venue: { 
              eq: "The Fillmore" 
            } 
          } 
        ) {
          edges {
            node {
              venue
            }
          }
        }
      }
    }
  }
}
```

Each field has their own set of filters.  Most fields have the following:

* eq - Equals.
* neq - Not equals.
* lt - Less than.
* lte - Less than or equal to.
* gt - Greater than.
* gte - Greater than or equal to.
* isnull - Is null.  If value is true, the field must be null.  If value is false, the field must not be null.
* between - Between.  Identical to using gte & lte on the same field.  Give values as `low, high`.
* in - Exists within an array.
* notin - Does not exist within an array.
* startwith - A like query with a wildcard on the right side of the value.
* endswith - A like query with a wildcard on the left side of the value.
* contains - A like query.

You may [exclude any filter](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/attributes.html#entity) from any entity, association, or globally.

Events
------

### Query Builder

You may modify the query builder used to resolve any connection by subscribing to events.
Each connection may have a unique event name.  `Entity::class . '.queryBuilder'` is recommended.
Pass as the second parameter to `$driver->resolve()`.

```php
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder;
use App\ORM\Entity\Artist;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'query',
        'fields' => [
            'artists' => [
                'type' => $driver->connection($driver->type(Artist::class)),
                'args' => [
                    'filter' => $driver->filter(Artist::class),
                    'pagination' => $driver->pagination(),
                ],
                'resolve' => $driver->resolve(Artist::class, Artist::class . '.queryBuilder'),
            ],
        ],
  ]),
]);

$driver->get(EventDispatcher::class)->subscribeTo(Artist::class . '.queryBuilder',
    function(QueryBuilder $event) {
        $event->getQueryBuilder()
            ->innerJoin('entity.user', 'user')
            ->andWhere($event->getQueryBuilder()->expr()->eq('user.id', ':userId'))
            ->setParameter('userId', currentUser()->getId())
            ;
    }
);
```

### Association Criteria

You may modify the criteria object used to filter associations.  For instance, if you use soft
deletes then you would want to filter out deleted rows from an association.

```php
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Criteria;
use App\ORM\Entity\Artist;
use League\Event\EventDispatcher;

#[GraphQL\Entity]
class Artist
{
    #[GraphQL\Field]
    public $id;

    #[GraphQL\Field]
    public $name;

    #[GraphQL\Association(filterCriteriaEventName: self::class . '.performances.filterCriteria')]
    public $performances;
}

// Add a listener to your driver
$driver->get(EventDispatcher::class)->subscribeTo(
    Artist::class . '.performances.filterCriteria',
    function (Criteria $event): void {
        $event->getCriteria()->andWhere(
            $event->getCriteria()->expr()->eq('isDeleted', false)
        );
    },
);
```

### Entity ObjectType Definition

You may modify the array used to define an entity type before it is created. This can be used for generated data and the like.
You must attach to events before defining your GraphQL schema.  See the [detailed documentation](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/events.html#modify-an-entity-definition) for details.

```php
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use App\ORM\Entity\Artist;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use League\Event\EventDispatcher;

$driver = new Driver($entityManager);

$driver->get(EventDispatcher::class)->subscribeTo(
    Artist::class . '.definition',
    static function (EntityDefinition $event): void {
        $definition = $event->getDefinition();

        // In order to modify the fields you must resovle the closure
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
```

Further Reading
---------------

Please read the [detailed documentation](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/).
