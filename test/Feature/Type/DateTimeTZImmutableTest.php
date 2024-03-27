<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZImmutable as DateTimeType;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DateTimeTZImmutableTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateTimeType = new DateTimeType();
        $control      = DateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result       = $dateTimeType->parseValue('2020-03-01T00:00:00+00:00');

        $this->assertEquals($control, $result);
    }

    public function testParseValueNull(): void
    {
        $this->expectException(Error::class);

        $dateTimeType = new DateTimeType();
        $result       = $dateTimeType->parseValue(null);
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateTimeType();
        $result   = $dateType->parseValue('03/01/2020');
    }

    public function testBetween(): void
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

        $now    = (new DateTime())->format('Y-m-d');
        $query  = '{ typetest ( filter: { testDateTimeTZImmutable: { between: { from: "2022-08-06" to: "' . $now . '" } } } ) { edges { node { id testDateTimeTZImmutable } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
