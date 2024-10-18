<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeImmutable;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DateTimeImmutableTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateImmutableType = new DateTimeImmutable();
        $control           = PHPDateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result            = $dateImmutableType->parseValue('2020-03-01T00:00:00+00:00');

        $this->assertEquals($control, $result);
    }

    public function testParseValueNull(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateTimeImmutable();
        $result   = $dateType->parseValue(null);
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateTimeImmutable();
        $result   = $dateType->parseValue('2023-11-26');
    }

    public function testParseLiteralNull(): void
    {
        $dateTimeType = new DateTimeImmutable();
        $node         = new StringValueNode([]);
        $node->value  = '';
        $result       = $dateTimeType->parseLiteral($node);

        $this->AssertNull($result);
    }

    public function testParseLiteralInvalid(): void
    {
        $this->expectException(Error::class);

        $dateTimeType = new DateTimeImmutable();
        $node         = new StringValueNode([]);
        $node->value  = 'invalid';
        $result       = $dateTimeType->parseLiteral($node);
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

        $now    = (new PHPDateTime())->format('c');
        $query  = '
          query ($from: DateTimeImmutable, $to: DateTimeImmutable) {
            typetest (
              filter: {
                testDateTimeImmutable: {
                  between: {
                    from: $from to: $to
                  }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateTimeImmutable
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
                'to' => $now,
            ],
        );

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']['edges']));
        $this->assertEquals(1, $data['typetest']['edges'][0]['node']['id']);

        // Test parseLiteral
        $now    = (new PHPDateTime())->format('c');
        $query  = '
          {
            typetest (
              filter: {
                testDateTimeImmutable: {
                  between: {
                    from: "2022-08-06T00:00:00+00:00" to: "' . $now . '"
                  }
                }
              }
            ) {
              edges {
                node {
                  id
                  testDateTimeImmutable
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
