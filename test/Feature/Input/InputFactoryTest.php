<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Input;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input as InputException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\EntityManager;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function array_keys;
use function array_unique;

class InputFactoryTest extends TestCase
{
    /**
     * Two inputs for the same entity with different fields have different
     * type names, so they can be used in one schema
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

    public function testInputWithAliasedRequiredField(): void
    {
        $config = new Config(['group' => 'InputFactoryAliasTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInputAlias' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, ['name'])),
                        ],
                        'resolve' => static function ($root, $args) use ($driver): User {
                            $user = $driver->get(EntityManager::class)
                                ->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['nameAlias']);
                            $driver->get(EntityManager::class)->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation TestInputAlias($id: ID!, $nameAlias: String!) {
            testInputAlias(id: $id, input: { nameAlias: $nameAlias }) {
                id
                nameAlias
            }
        }';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['id' => 1, 'nameAlias' => 'inputAliasTest'],
            operationName: 'TestInputAlias',
        );

        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputAliasTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInputAlias']['id']);
        $this->assertEquals('inputAliasTest', $output['data']['testInputAlias']['nameAlias']);
    }

    public function testInputExcludesIdentifier(): void
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
        $output = $result->toArray();

        $this->assertEquals($output['errors'][0]['message'], 'Identifier id is an invalid input. Identifiers should not be included in mutation input.');
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

    public function testInputWithAliasedOptionalField(): void
    {
        $config = new Config(['group' => 'InputFactoryAliasTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'testInputAlias' => [
                        'type' => $driver->type(User::class),
                        'args' => [
                            'id' => Type::nonNull(Type::id()),
                            'input' => Type::nonNull($driver->input(User::class, [], ['name'])),
                        ],
                        'resolve' => function ($root, $args): User {
                            $user = $this->getEntityManager()->getRepository(User::class)
                                ->find($args['id']);

                            $user->setName($args['input']['nameAlias']);
                            $this->getEntityManager()->flush();

                            return $user;
                        },
                    ],
                ],
            ]),
        ]);

        $query = 'mutation {
            testInputAlias(id: 1, input: { nameAlias: "inputAliasTest" }) {
                id
                nameAlias
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        $this->getEntityManager()->clear();
        $user = $this->getEntityManager()->getRepository(User::class)
            ->find(1);

        $this->assertEquals('inputAliasTest', $user->getName());
        $this->assertEquals(1, $output['data']['testInputAlias']['id']);
        $this->assertEquals('inputAliasTest', $output['data']['testInputAlias']['nameAlias']);
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

    public function testInputThrowsExceptionIfIdentifierFound(): void
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
                            'input' => Type::nonNull($driver->input(User::class, [], ['id', 'email', 'password'])),
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

        $this->assertEquals($output['errors'][0]['message'], 'Identifier id is an invalid input. Identifiers should not be included in mutation input.');
    }

    /**
     * With no field lists, a column which is not exposed in the group is
     * left out of the input rather than crashing the build
     */
    public function testInputWithAllFieldsSkipsUnexposedField(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryUnexposedTest']));

        $input = $driver->input(User::class);

        $this->assertEqualsCanonicalizing(['name', 'email'], array_keys($input->getFields()));
    }

    /**
     * Naming a column which is not exposed in the group is an error
     */
    public function testInputWithUnexposedRequiredFieldThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryUnexposedTest']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Field password is not exposed');

        $driver->input(User::class, ['password'])->getFields();
    }

    /**
     * Naming a column which is not exposed in the group is an error
     */
    public function testInputWithUnexposedOptionalFieldThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryUnexposedTest']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Field password is not exposed');

        $driver->input(User::class, [], ['password'])->getFields();
    }

    /**
     * A name which is not a field of the entity, such as a typo, is an error
     * with a suggestion rather than being silently ignored
     */
    public function testInputWithUnknownRequiredFieldThrowsExceptionWithSuggestion(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Field nmae is not a field of entity ' . User::class . '. Did you mean "name"?');

        $driver->input(User::class, ['nmae'])->getFields();
    }

    public function testInputWithUnknownOptionalFieldThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Field nmae is not a field of entity ' . User::class . '. Did you mean "name"?');

        $driver->input(User::class, ['email'], ['nmae'])->getFields();
    }

    /**
     * No suggestion is made when nothing is similar
     */
    public function testInputWithUnknownFieldAndNoSimilarFieldThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        try {
            $driver->input(User::class, ['zzzzzzzz'])->getFields();
            $this->fail('An exception was expected');
        } catch (InputException $e) {
            $this->assertSame('Field zzzzzzzz is not a field of entity ' . User::class . '.', $e->getMessage());
        }
    }

    /**
     * Input type names are derived from the fields, so they are the same on
     * every build
     */
    public function testInputTypeNameIsDeterministic(): void
    {
        $driver1 = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));
        $driver2 = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $this->assertSame(
            $driver1->input(User::class, ['name'], ['email'])->name,
            $driver2->input(User::class, ['name'], ['email'])->name,
        );
    }

    public function testInputWithoutFieldListsIsNamedTypeInput(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $this->assertSame(
            'ApiSkeletonsTest_Doctrine_ORM_GraphQL_Entity_User_InputFactoryTest_Input',
            $driver->input(User::class)->name,
        );
    }

    /**
     * The same call returns the same type, so it can be used by several
     * mutations in one schema.  The order of the fields does not matter.
     */
    public function testSameFieldsReturnSameInputType(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $input = $driver->input(User::class, ['name', 'email']);

        $this->assertSame($input, $driver->input(User::class, ['name', 'email']));
        $this->assertSame($input, $driver->input(User::class, ['email', 'name']));

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'query', 'fields' => ['user' => $driver->completeConnection(User::class)]]),
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'createUser' => [
                        'type' => $driver->type(User::class),
                        'args' => ['input' => Type::nonNull($driver->input(User::class, ['name', 'email']))],
                    ],
                    'updateUser' => [
                        'type' => $driver->type(User::class),
                        'args' => ['input' => Type::nonNull($driver->input(User::class, ['name', 'email']))],
                    ],
                ],
            ]),
        ]);

        $schema->assertValid();
    }

    public function testDifferentFieldsReturnDifferentInputTypes(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $names = [
            $driver->input(User::class)->name,
            $driver->input(User::class, ['name'])->name,
            $driver->input(User::class, ['email'])->name,
            $driver->input(User::class, [], ['name'])->name,
            $driver->input(User::class, ['name'], ['email'])->name,
        ];

        $this->assertSame($names, array_unique($names));
    }

    public function testInputWithName(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $input = $driver->input(User::class, ['name', 'email'], [], 'CreateUserInput');

        $this->assertSame('CreateUserInput', $input->name);
        $this->assertSame($input, $driver->input(User::class, ['email', 'name'], [], 'CreateUserInput'));
        $this->assertNotSame($input, $driver->input(User::class, ['name', 'email']));
    }

    public function testInputNameReusedForDifferentFieldsThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $driver->input(User::class, ['name'], [], 'UserInput');

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Input type name UserInput is already used for different fields');

        $driver->input(User::class, ['email'], [], 'UserInput');
    }

    public function testInvalidInputNameThrowsException(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InputFactoryTest']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Input type name create-user is not a valid GraphQL name');

        $driver->input(User::class, ['name'], [], 'create-user');
    }
}
