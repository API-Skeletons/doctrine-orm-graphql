<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorFactory;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\NamingStrategy\CustomNamingStrategy;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class NamingStrategyTest extends AbstractTest
{
    public function testNamingStrategy(): void
    {
        $config = new Config(['group' => 'NamingStrategyTest']);

        $driver = new Driver($this->getEntityManager(), $config);
        $driver->get(HydratorFactory::class)
            ->set(CustomNamingStrategy::class, new CustomNamingStrategy());

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'user' => [
                        'type' => $driver->connection(User::class),
                        'args' => [
                            'filter' => $driver->filter(User::class),
                        ],
                        'resolve' => $driver->resolve(User::class),
                    ],
                ],
            ]),
        ]);

        $query = '{ user { edges { node { name email } } } }';

        $result = GraphQL::executeQuery($schema, $query);
        $output = $result->toArray();

        foreach ($output['data']['user']['edges'] as $edge) {
            $this->assertNotEmpty($edge['node']['name']);
        }
    }
}
