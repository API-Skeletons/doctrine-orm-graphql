GraphQL for Doctrine Using Attributes
=====================================

[![Build Status](https://github.com/API-Skeletons/doctrine-graphql/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/API-Skeletons/doctrine-graphql/actions/workflows/continuous-integration.yml?query=branch%3Amain)
[![Code Coverage](https://codecov.io/gh/API-Skeletons/doctrine-graphql/branch/main/graphs/badge.svg)](https://codecov.io/gh/API-Skeletons/doctrine-graphql/branch/main)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2b-blue)](https://img.shields.io/badge/PHP-8.0%2b-blue)
[![Total Downloads](https://poser.pugx.org/api-skeletons/doctrine-graphql/downloads)](//packagist.org/packages/api-skeletons/doctrine-graphql)
[![License](https://poser.pugx.org/api-skeletons/doctrine-graphql/license)](//packagist.org/packages/api-skeletons/doctrine-graphql)


Using PHP 8 attributes on your entities, this library will create a 
Doctrine driver for use with [webonyx/graphql-php](https://github.com/webonyx/graphql-php).  This library
enables GraphQL on Doctrine with a minimum amount of configuration.  

This README file describes how to start.   [Detailed documentation](https://apiskeletons-doctrine-graphql.readthedocs.io/en/latest/)
is also available.

Installation
------------

Run the following to install this library using [Composer](https://getcomposer.org/):

```bash
composer require api-skeletons/doctrine-graphql
```

Quickest Start
--------------

```php
use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Driver;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

$driver = new Driver($entityManager, new Config([
    'globalEnable' => true,
]);

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'query',
        'fields' => [
            'artist' => [
                'type' => $driver->connection($driver->type(Artist::class)),
                'args' => [
                    'filter' => $driver->filter(Artist::class),
                ],
                'resolve' => $driver->resolve(Artist::class),
            ],
        ],
    ]),
]);

$query = '{ 
    artist { 
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

$result = GraphQL::executeQuery($schema, $query);
$output = $result->toArray();

```

This Quickest Start example uses a feature of this library that turns an entire Doctrine schema
into GraphQL without any configuration of the entities by enabling  
[globalEnable](https://apiskeletons-doctrine-graphql.readthedocs.io/en/latest/config.html#globalenable),
intended only for development, in the driver configuration.



Quick Start
-----------

For finer control over your GraphQL, add attributes to your Doctrine entities

```php
use ApiSkeletons\Doctrine\GraphQL\Attribute as GraphQL;

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
use ApiSkeletons\Doctrine\GraphQL\Driver;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

$driver = new Driver($entityManager);

$schema = new Schema([
    'query' => new ObjectType([
        'name' => 'query',
        'fields' => [
            'artist' => [
                'type' => $driver->connection($driver->type(Artist::class)),
                'args' => [
                    'filter' => $driver->filter(Artist::class),
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
                'resolve' => function ($root, $args): User {
                    $artist = $this->getEntityManager()->getRepository(Artist::class)
                        ->find($args['id']);

                    $artist->setName($args['input']['name']);
                    $this->getEntityManager()->flush();

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

$query = '{ artist { edges { node { id name performances { edges { node { venue } } } } } } }';

$result = GraphQL::executeQuery($schema, $query);
$output = $result->toArray();
```

Run GraphQL mutations

```php
use GraphQL\GraphQL;

$query = 'mutation {
    artistUpdateName(id: 1, input: { name: "newName" }) {
        id
        name
    }
}';

$result = GraphQL::executeQuery($schema, $query);
$output = $result->toArray();
```



Filtering
---------

For every attributed field and every attributed association, filters are available in your
GraphQL query.

Example

```gql
{
  artist (filter: { name_contains: "dead" }) {
    edges {
      node {
        id
        name
        performances (filter: { venue_eq: "The Fillmore" }) {
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

Filters are named after their field and followed by an underscore and the filter name.

* eq - Equals.  May be shorthanded as `field: "value"` or longhanded as `field_eq: "value"`
* neq - Not equals
* lt - Less than
* lte - Less than or equal to
* gt - Greater than
* gte - Greater than or equal to
* isnull - Is null.  If value is true, the field must be null.  If value is false, the field must not be null.
* between - Between.  Identical to using gte & lte on the same field.  Give values as `low,high`
* in - Exists within a list of comma-delimited values.
* notin - Does not exist within a list of comma-delimited values.
* startwith - A like query with a wildcard on the right side of the value.
* endswith - A like query with a wildcard on the left side of the value.
* contains - A like query.


Further Reading
---------------

[Detailed documentation](https://apiskeletons-doctrine-graphql.readthedocs.io/en/latest/)
is available.
