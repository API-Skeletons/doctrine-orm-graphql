<?php

namespace ApiSkeletonsTest\Doctrine\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\GraphQL\Config;
use ApiSkeletons\Doctrine\GraphQL\Driver;
use ApiSkeletons\Doctrine\GraphQL\Type\Date;
use ApiSkeletons\Doctrine\GraphQL\Type\DateImmutable;
use ApiSkeletonsTest\Doctrine\GraphQL\Entity\TypeTest;
use DateTime as PHPDateTime;
use ApiSkeletonsTest\Doctrine\GraphQL\AbstractTest;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class DateImmutableTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateImmutableType = new DateImmutable();
        $control = PHPDateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result = $dateImmutableType->parseValue('2020-03-01');

        $this->assertEquals($control->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateImmutable();
        $result = $dateType->parseValue(true);
    }

    public function testBetween(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'DataTypesTest',
        ]));
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

        $now = (new \DateTime())->format('Y-m-d');
        $query = '{ typetest ( filter: { testDateImmutable_between: { from: "2022-08-06" to: "' . $now . '" } } ) { edges { node { id testDateImmutable } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
