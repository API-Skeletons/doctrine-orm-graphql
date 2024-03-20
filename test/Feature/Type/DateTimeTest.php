<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTime as DateTimeType;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DateTimeTest extends AbstractTest
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

        $query  = '
          query ($from: DateTime!, $to: DateTime!) {
            typetest (
              filter: {
                testDateTime: {
                  between: {
                    from: $from to: $to
                  }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateTime
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
            variableValues: [
                'from' => '2022-08-06T00:00:00+00:00',
                'to' => (new DateTime())->format('c'),
            ],
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);

        // Test parseLiteral
        $query  = '
          {
            typetest (
              filter: {
                testDateTime: {
                  between: {
                    from: "2022-08-06T00:00:00+00:00" to: "' . (new DateTime())->format('c') . '"
                  }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateTime
                }
              }
            }
          }
        ';
        $result = GraphQL::executeQuery(
            schema: $schema,
            source: $query,
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);
    }
}
