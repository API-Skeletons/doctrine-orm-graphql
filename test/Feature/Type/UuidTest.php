<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Uuid;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Schema;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

use function count;

class UuidTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $uuid = RamseyUuid::uuid4()->toString();

        $uuidType = new Uuid();

        $uuidObject = $uuidType->parseValue($uuid);

        $this->assertInstanceOf(UuidInterface::class, $uuidObject);
        $this->assertEquals($uuid, $uuidObject->toString());
    }

    public function testParseValueUuidInterface(): void
    {
        $uuid = RamseyUuid::uuid4();

        $uuidType = new Uuid();

        $this->assertEquals($uuid, $uuidType->parseValue($uuid));
    }

    public function testSerializeWithString(): void
    {
        $uuid = RamseyUuid::uuid4()->toString();

        $uuidType = new Uuid();

        $this->assertEquals($uuid, $uuidType->serialize($uuid));
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $uuidType = new Uuid();
        $result   = $uuidType->parseValue('invalid uuid');
    }

    public function testParseLiteral(): void
    {
        $uuidType    = new Uuid();
        $node        = new StringValueNode([]);
        $node->value = 'search string';
        $result      = $uuidType->parseLiteral($node);

        $this->assertEquals($node->value, $result);
    }

    public function testQuery(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => [
                        'type' => $driver->connection($driver->type(TypeTest::class)),
                        'args' => [
                            'filter' => $driver->filter(TypeTest::class),
                        ],
                        'resolve' => $driver->resolve(TypeTest::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ typetest ( filter: { testUuid: { sort: "ASC" } } ) { edges { node { id testUuid } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }

    public function testMutation(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'mutation' => new ObjectType([
                'name' => 'mutation',
                'fields' => [
                    'typetest' => [
                        'type' => $driver->type(TypeTest::class),
                        'args' => [
                            'uuid' => $driver->type('uuid'),
                        ],
                        'resolve' => function ($root, array $args, $context, ResolveInfo $info) use ($driver) {
                            // This tests the Uuid type for changing a string into a UuidInterface
                            $this->assertInstanceOf(UuidInterface::class, $args['uuid']);

                            return $driver->get(EntityManager::class)->getRepository(TypeTest::class)->find(1);
                        },
                    ],
                ],
            ]),
        ]);

        $query  = '
            mutation TestUuid ($uuid: Uuid) {
                typetest (uuid: $uuid) {
                    id
                    testUuid
                }
            }
        ';
        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: ['uuid' => RamseyUuid::uuid4()->toString()],
            operationName: 'TestUuid',
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(1, $data['typetest']['id']);
    }
}
