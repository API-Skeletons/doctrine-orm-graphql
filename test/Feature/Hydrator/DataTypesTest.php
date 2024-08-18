<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function count;

class DataTypesTest extends AbstractTest
{
    public function testDataTypes(): void
    {
        $config = new Config(['group' => 'DataTypesTest']);

        $driver = new Driver($this->getEntityManager(), $config);

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

        $query = '{
            typetest {
                edges {
                     node {
                        testInt
                        testFloat
                        testBool
                        testText
                        testBigint
                        testDecimal
                        testJson
                        testSimpleArray
                        testSmallInt

                        testDate
                        testDateTime
                        testDateTimeTZ
                        testTime

                        testDateTimeImmutable
                        testDateImmutable
                        testDateTimeTZImmutable
                        testTimeImmutable

                    }
                }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals(1, count($data['typetest']));
    }
}
