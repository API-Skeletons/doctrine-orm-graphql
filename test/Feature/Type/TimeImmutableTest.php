<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TimeImmutable;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class TimeImmutableTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $timeImmutable = new TimeImmutable();
        $control      = DateTime::createFromFormat('Y-m-d\TH:i:s.uP', '2020-03-01T20:12:15.123456+00:00');
        $result       = $timeImmutable->parseValue('20:12:15.123456');

        $this->assertEquals($control->format('H:i:s.u'), $result->format('H:i:s.u'));
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $timeImmutable = new TimeImmutable();
        $result   = $timeImmutable->parseValue(true);
    }

    public function testBetween(): void
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

        $query  = '{ typetest ( filter: { testTimeImmutable: { between: { from: "19:15:10.000000" to: "21:00:00.000000" } } } ) { edges { node { id testDate } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
