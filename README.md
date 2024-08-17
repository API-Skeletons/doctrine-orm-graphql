<p align="center">
    <img src="https://placehold.co/10x10/337ab7/337ab7.png" width="100%" height="15px">
    <img src="https://raw.githubusercontent.com/api-skeletons/doctrine-orm-graphql/master/docs/banner.png" width="450px">
</p>


GraphQL Type Driver for Doctrine ORM
====================================

[![Build Status](https://github.com/API-Skeletons/doctrine-orm-graphql/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/API-Skeletons/doctrine-orm-graphql/actions/workflows/continuous-integration.yml?query=branch%3Amain)
[![Code Coverage](https://codecov.io/gh/API-Skeletons/doctrine-orm-graphql/branch/main/graphs/badge.svg)](https://codecov.io/gh/API-Skeletons/doctrine-orm-graphql/branch/main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/API-Skeletons/doctrine-orm-graphql/badges/quality-score.png?b=12.0.x)](https://scrutinizer-ci.com/g/API-Skeletons/doctrine-orm-graphql/?branch=12.0.x)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2b-blue)](https://img.shields.io/badge/PHP-8.1%2b-blue)
[![Total Downloads](https://poser.pugx.org/api-skeletons/doctrine-orm-graphql/downloads)](//packagist.org/packages/api-skeletons/doctrine-orm-graphql)
[![License](https://poser.pugx.org/api-skeletons/doctrine-orm-graphql/license)](//packagist.org/packages/api-skeletons/doctrine-orm-graphql)

This library provides a GraphQL driver for Doctrine ORM for use with the [webonyx/graphql-php](https://github.com/webonyx/graphql-php) library.  It **does not** try to redefine how that excellent library operates.  Instead, it creates types to be used within the framework that library provides.


Installation
------------

Via composer:

```bash
composer require api-skeletons/doctrine-orm-graphql
```


Documentation
-------------

Full documentation is available at https://doctrine-orm-graphql.apiskeletons.dev or in the [docs](https://github.com/api-skeletons/doctrine-orm-graphql/blob/master/docs) directory.


Versions
--------

* 12.0.x - Supports [league/event](https://github.com/thephpleague/event) version 3.0 and is PSR-14 compliant
* 11.0.x - Supports [league/event](https://github.com/thephpleague/event) version 2.2

More information [in the documentation](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/versions.html).


Examples
--------

The **LDOG Stack**: Laravel, Doctrine ORM, and GraphQL uses this library:  https://ldog.apiskeletons.dev

For an working implementation see https://graphql.lcdb.org and the corresonding application at https://github.com/lcdborg/graphql.lcdb.org.


Features
--------

* Supports all [Doctrine Types](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/types.html#data-type-mappings) and allows custom types
* Pagination with the [GraphQL Complete Connection Model](https://graphql.org/learn/pagination/#complete-connection-model)
* [Filtering of sub-collections](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/queries.html)
* [Events](https://github.com/API-Skeletons/doctrine-orm-graphql#events) for modifying queries, entity types and more
* [Multiple configuration group support](https://doctrine-orm-graphql.apiskeletons.dev/en/latest/driver.html#group)


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
            'artists' => $driver->completeConnection(Artist::class),
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


Filters
-------

For every enabled field and association, filters are available for querying.

Example

```gql
{
  artists (
    filter: {
      name: {
        contains: "Dead"
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

Each field has their own set of filters.  Based on the field type, some or all of the following filters are available:

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


History
-------

The roots of this project go back to May 2018 with https://github.com/API-Skeletons/zf-doctrine-graphql; written for Zend Framework 2.  It was migrated to the framework agnostic https://packagist.org/packages/api-skeletons/doctrine-graphql but the name of that repository was incorrect because it did not specify ORM only.  So this repository was created and the others were abandoned.


License
-------

See [LICENSE](https://github.com/api-skeletons/doctrine-orm-graphql/blob/master/LICENSE).

