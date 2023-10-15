<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ArrayObject;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use Psr\Container\ContainerInterface;

class DriverTest extends AbstractTest
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

        $this->assertInstanceOf(Driver::class, $driver);
        $this->assertInstanceOf(ArrayObject::class, $driver->get('metadata'));
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, User::class));
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, Artist::class));
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, Performance::class));
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, Recording::class));
    }

    public function testCreateDriverWithConfig(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $config    = new Config([
            'group' => 'default',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $driver = new Driver($this->getEntityManager(), $config, [], $container);

        $this->assertInstanceOf(Driver::class, $driver);
        $this->assertInstanceOf(ArrayObject::class, $driver->get('metadata'));
    }

    public function testNonDefaultGroup(): void
    {
        $config = new Config([
            'group' => 'testNonDefaultGroup',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $driver = new Driver($this->getEntityManager(), $config);
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, User::class));

        $this->expectException(Error::class);
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, Artist::class));
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
                        'type' => $driver->connection($driver->type(Artist::class)),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                            'pagination' => $driver->pagination(),
                        ],
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
            artist (filter: { name: { contains: "dead" } })
                { edges { node { id name performances { edges { node { venue recordings { edges { node { source } } } } } } } } }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->assertEquals('Grateful Dead', $output['data']['artist']['edges'][0]['node']['name']);
    }
}
