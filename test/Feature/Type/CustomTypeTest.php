<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\Strategy\CsvString;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function is_string;

class CustomTypeTest extends AbstractTest
{
    public function testCustomFieldType(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CustomTypeTest']));
        $driver->get(TypeContainer::class)->set('customtype', static fn () => Type::string());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typeTest' => [
                        'type' => $driver->connection(TypeTest::class),
                        'args' => [
                            'filter' => $driver->filter(TypeTest::class),
                        ],
                        'resolve' => $driver->resolve(TypeTest::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ typeTest { edges { node { testFloat } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertTrue(is_string($data['typeTest']['edges'][0]['node']['testFloat']));
    }

    public function testCustomFieldTypeArray(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CustomTypeArray']));
        $driver->get(TypeContainer::class)->set('csvstring', static fn () => Type::listOf(Type::string()));
        $driver->get(HydratorContainer::class)->set(CsvString::class, static fn () => new CsvString());

        $this->getEntityManager()->getRepository(TypeTest::class)
            ->find(1)->setTestText('one,two,three');
        $this->getEntityManager()->flush();

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typeTest' => [
                        'type' => $driver->connection(TypeTest::class),
                        'args' => [
                            'filter' => $driver->filter(TypeTest::class),
                        ],
                        'resolve' => $driver->resolve(TypeTest::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ typeTest { edges { node { testText } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertIsArray($data['typeTest']['edges'][0]['node']['testText']);
        $this->assertCount(3, $data['typeTest']['edges'][0]['node']['testText']);
    }
}
