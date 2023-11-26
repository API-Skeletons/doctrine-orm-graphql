<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Date;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeImmutable;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DateTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateType = new Date();
        $control  = PHPDateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result   = $dateType->parseValue('2020-03-01');

        $this->assertEquals($control->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateType = new Date();
        $result   = $dateType->parseValue('03/01/2020');
    }

    public function testParseValueNull(): void
    {
        $this->expectException(Error::class);

        $dateType = new Date();
        $result   = $dateType->parseValue(null);
    }

    public function testSerializeString(): void
    {
        $this->expectException(Error::class);
        $dateType = new Date();

        $dateType->serialize('invalid string');
    }

    public function testSerializeNonDateTimeObject(): void
    {
        $this->expectException(Error::class);
        $dateType = new Date();

        $dateType->serialize(new DateTimeImmutable());
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

        $now    = (new PHPDateTime())->format('Y-m-d');
        $query  = '{ typetest ( filter: { testDate: { between: { from: "2022-08-06" to: "' . $now . '" } } } ) { edges { node { id testDate } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
