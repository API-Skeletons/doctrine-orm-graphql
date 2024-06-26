<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class FiltersTypeCollisionTest extends AbstractTest
{
    public function testFiltersTypeCollision(): void
    {
        $driver1 = new Driver($this->getEntityManager());
        $driver2 = new Driver($this->getEntityManager(), new Config(['group' => 'ExcludeFiltersTest']));

        $driver2->set(TypeContainer::class, $driver1->get(TypeContainer::class));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance1' => [
                        'type' => $driver1->connection(Performance::class),
                        'args' => [
                            'filter' => $driver1->filter(Performance::class),
                            'pagination' => $driver1->pagination(),
                        ],
                        'resolve' => $driver1->resolve(Performance::class),
                    ],
                    'performance2' => [
                        'type' => $driver2->connection(Performance::class),
                        'args' => [
                            'filter' => $driver2->filter(Performance::class),
                            'pagination' => $driver2->pagination(),
                        ],
                        'resolve' => $driver2->resolve(Performance::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{
            one: performance1 (
              filter: {
                id: {
                  eq: 2
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
              pageInfo {
                hasNextPage
              }
            },
            two: performance2 (
              filter: {
                id: {
                  eq: 2
                }
              }
            ) {
              edges {
                node {
                  id
                }
              }
              pageInfo {
                hasNextPage
              }
            }
        }';
        $result = GraphQL::executeQuery($schema, $query);

        $data = $result->toArray()['data'];

        $this->assertEquals($data['one']['edges'][0]['node']['id'], $data['two']['edges'][0]['node']['id']);
        $this->assertSame($driver1->get(TypeContainer::class), $driver2->get(TypeContainer::class));
        $this->assertSame(
            $driver1->get(TypeContainer::class)->get('pageinfo'),
            $driver2->get(TypeContainer::class)->get('pageinfo'),
        );
        $this->assertSame(
            $driver1->get(TypeContainer::class)->get('pagination'),
            $driver2->get(TypeContainer::class)->get('pagination'),
        );
    }
}
