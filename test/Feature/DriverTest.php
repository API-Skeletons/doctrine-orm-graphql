<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use ArrayObject;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\BooleanType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Psr\Container\ContainerInterface;

use function array_keys;
use function array_map;

class DriverTest extends TestCase
{
    public function testGetInvalidService(): void
    {
        $driver = new Driver($this->getEntityManager());

        $this->expectException(Error::class);
        $driver->get('invalid');
    }

    public function testCreateDriverWithoutConfig(): void
    {
        $driver = new Driver($this->getEntityManager());

        $entityTypeContainer = $driver->get(EntityTypeContainer::class);

        $this->assertInstanceOf(Driver::class, $driver);
        $this->assertInstanceOf(ArrayObject::class, $driver->get(Metadata::class));
        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(User::class));
        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(Artist::class));
        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(Performance::class));
        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(Recording::class));
    }

    public function testCreateDriverWithConfig(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $config    = new Config([
            'group' => 'default',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $driver = new Driver($this->getEntityManager(), $config, [], $container);

        $this->assertInstanceOf(Driver::class, $driver);
        $this->assertInstanceOf(ArrayObject::class, $driver->get(Metadata::class));
    }

    public function testNonDefaultGroup(): void
    {
        $config = new Config([
            'group' => 'testNonDefaultGroup',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $driver              = new Driver($this->getEntityManager(), $config);
        $entityTypeContainer = $driver->get(EntityTypeContainer::class);

        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(User::class));

        $this->expectException(Error::class);
        $this->assertInstanceOf(Entity::class, $entityTypeContainer->get(Artist::class));
    }

    /**
     * This tests much of the whole system.  Each part is tested in detail
     * elsewhere.
     */
    public function testBuildGraphQLSchema(): void
    {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => ['filter' => $driver->filter(Artist::class)] + $driver->pagination(),
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '{
            artist (filter: { name: { contains: "dead" } })
                { edges { node { id name performances { edges { node { venue recordings { edges { node { source } } } } } } } } }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEquals('Grateful Dead', $output['data']['artist']['edges'][0]['node']['name']);
    }

    public function testUseHydratorCache(): void
    {
        $config = new Config(['useHydratorCache' => true]);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query = '{
          artist (
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
                performances {
                  edges {
                    node {
                      venue
                        recordings {
                          edges {
                            node {
                              source
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEquals('Grateful Dead', $output['data']['artist']['edges'][0]['node']['name']);
    }

    public function testTypeMethodForCustomScalarValues(): void
    {
        $driver = new Driver($this->getEntityManager());

        $driver->get(TypeContainer::class)
            ->set('custom', static fn () => Type::boolean());

        $this->assertInstanceOf(BooleanType::class, $driver->type('custom'));
    }

    public function testTypeMethodInvalidType(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Type "custom" is not registered');

        $driver = new Driver($this->getEntityManager());

        $this->assertInstanceOf(BooleanType::class, $driver->type('custom'));
    }

    public function testCompleteConnection(): void
    {
        $driver = new Driver($this->getEntityManager());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $query = '{
            artist (filter: { name: { contains: "dead" } })
                { edges { node { id name performances { edges { node { venue recordings { edges { node { source } } } } } } } } }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEquals('Grateful Dead', $output['data']['artist']['edges'][0]['node']['name']);
    }

    public function testCustomEntityIsRegisteredAsAType(): void
    {
        $driver = new Driver($this->getEntityManager());

        $control = $driver->type(Artist::class, 'custom');
        $test    = $driver->type(Artist::class, 'custom');

        $this->assertSame($control, $test);
    }

    /**
     * The GraphQL Complete Connection Model places first, after, last and
     * before at the top level of a connection's arguments, for a top level
     * connection and for an association
     */
    public function testPaginationArgumentsAreTopLevel(): void
    {
        $driver = new Driver($this->getEntityManager());

        $this->assertSame(['first', 'after', 'last', 'before'], array_keys($driver->pagination()));
        $this->assertSame(
            ['filter', 'first', 'after', 'last', 'before'],
            array_keys($driver->completeConnection(Artist::class)['args']),
        );

        $performances = $driver->type(Artist::class)->getField('performances');
        $this->assertSame(
            ['filter', 'first', 'after', 'last', 'before'],
            array_map(static fn ($arg) => $arg->name, $performances->args),
        );
    }
}
