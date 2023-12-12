<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Input;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use Doctrine\ORM\EntityManager;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Throwable;

class InputFactoryTest extends AbstractTest
{
    /**
     * TypeNames for inputs was EntityType_Input but that has been changed to
     * EntityType_Input_uniqid to avoid collisions
     */
    public function testMultipeInputsWithSameEntity(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);
        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput1' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['name'])),
                        ],
                        'resolve' => static function ($root, $args) use ($driver): User {
                            $user = $driver->get(EntityManager::class)
                                ->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $driver->get(EntityManager::class)->flush();

                            return $user;
                        },
                    ],
                    'testInput2' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['email'])),
                        ],
                        'resolve' => static function ($root, $args) use ($driver): User {
                            $user = $driver->get(EntityManager::class)
                                ->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $driver->get(EntityManager::class)->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation TestInput($id: ID!, $name: String!) {
            testInput1(id: $id, input: { name: $name }) {
                id
                name
                email
            }
        }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => 1, 'name' => 'inputTest'],
            operationName: 'TestInput',
        );

        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput1']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput1']['name']);
    }

    public function testInputWithRequiredField(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['name'])),
                        ],
                        'resolve' => static function ($root, $args) use ($driver): User {
                            $user = $driver->get(EntityManager::class)
                                ->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $driver->get(EntityManager::class)->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation TestInput($id: ID!, $name: String!) {
            testInput(id: $id, input: { name: $name }) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => 1, 'name' => 'inputTest'],
            operationName: 'TestInput',
        );

        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput']['name']);
    }

    public function testInputExcludesIdentifier(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Identifier id is an invalid input.');

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['id'])),
                        ],
                        'resolve' => static function ($root, $args): void {
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInput(id: 1, input: { name: "inputTest" }) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
    }

    public function testInputWithOptionalField(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, [], ['name'])),
                        ],
                        'resolve' => function ($root, $args): User {
                            $user = $this->getEntityManager()->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $this->getEntityManager()->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInput(id: 1, input: { name: "inputTest" }) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput']['name']);
    }

    public function testInputWithAllFieldsRequired(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class)),
                        ],
                        'resolve' => function ($root, $args): User {
                            $user = $this->getEntityManager()->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $this->getEntityManager()->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInput(id: 1, input: { name: "inputTest" email: "email" password: "password"}) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput']['name']);
    }

    public function testInputWithAllFieldsRequiredExplicitly(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['name', 'email', 'password'])),
                        ],
                        'resolve' => function ($root, $args): User {
                            $user = $this->getEntityManager()->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $this->getEntityManager()->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInput(id: 1, input: { name: "inputTest" email: "email" password: "password"}) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput']['name']);
    }

    public function testInputWithAllFieldsOptional(): void
    {
        $config = new Config(['group' => 'InputFactoryTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInput' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, [], ['name', 'email', 'password'])),
                        ],
                        'resolve' => function ($root, $args): User {
                            $user = $this->getEntityManager()->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['name']);
                            $this->getEntityManager()->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInput(id: 1, input: { name: "inputTest" email: "email" password: "password"}) {
                id
                name
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInput']['id']);
        $this->assertEquals('inputTest', $output['data']['testInput']['name']);
    }
}
