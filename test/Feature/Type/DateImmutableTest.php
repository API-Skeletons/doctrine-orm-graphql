<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateImmutable;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DateImmutableTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateImmutableType = new DateImmutable();
        $control           = PHPDateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result            = $dateImmutableType->parseValue('2020-03-01');

        $this->assertEquals($control->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateImmutableType = new DateImmutable();
        $result            = $dateImmutableType->parseValue('03/01/2020');
    }

    public function testParseValueNull(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateImmutable();
        $result   = $dateType->parseValue(null);
    }

    public function testSerializeString(): void
    {
        $this->expectException(Error::class);
        $dateImmutableType = new DateImmutable();

        $dateImmutableType->serialize('invalid string');
    }

    public function testSerializeNonDateTimeObject(): void
    {
        $this->expectException(Error::class);
        $dateImmutableType = new DateImmutable();

        $dateImmutableType->serialize(new PHPDateTime());
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

        $now   = (new PHPDateTime())->format('Y-m-d');
        $query = '
          query TypeTest($from: DateImmutable!, $to: DateImmutable!) {
            typetest (
              filter: {
                testDateImmutable: {
                  between: { from: $from, to: $to }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateImmutable
                }
              }
            }
          }
        ';

        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: [
                'from' => '2022-08-06',
                'to' => $now,
            ],
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }

    public function testBetweenLiteral(): void
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
        $query  = '
          {
            typetest (
              filter: {
                testDateImmutable: {
                  between: { from: "2022-08-06" to: "' . $now . '" }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateImmutable
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
