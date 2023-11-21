<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Uuid;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
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
        $result   = $uuidType->parseValue(true);
    }

    public function testParseLiteral(): void
    {
        $uuidType    = new Uuid();
        $node        = new StringValueNode([]);
        $node->value = 'search string';
        $result      = $uuidType->parseLiteral($node);

        $this->assertEquals($node->value, $result);
    }

    public function testContains(): void
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
}
