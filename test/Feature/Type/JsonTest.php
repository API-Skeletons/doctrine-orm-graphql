<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Json;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class JsonTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $jsonType = new Json();

        $control = ['array' => 'test', 'in' => ['json']];
        $result  = $jsonType->parseValue('{"array": "test", "in": ["json"]}');

        $this->assertEquals($control, $result);
    }

    public function testParseValueInvalidNull(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Json();
        $result   = $jsonType->parseValue(null);
    }

    public function testParseValueInvalidJson(): void
    {
        $this->expectException(Error::class);

        $jsonType = new Json();
        $result   = $jsonType->parseValue('{"field": "value}');
    }

    public function testParseLiteral(): void
    {
        $this->expectException(Error::class);

        $jsonType    = new Json();
        $node        = new StringValueNode([]);
        $node->value = 'search string';
        $result      = $jsonType->parseLiteral($node);
    }

    public function testContains(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => [
                        'type' => $driver->connection(TypeTest::class),
                        'args' => [
                            'filter' => $driver->filter(TypeTest::class),
                        ],
                        'resolve' => $driver->resolve(TypeTest::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ typetest ( filter: { testJson: { sort: "ASC" } } ) { edges { node { id testJson } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
