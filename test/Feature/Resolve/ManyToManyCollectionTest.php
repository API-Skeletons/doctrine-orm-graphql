<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_column;
use function array_map;
use function sort;

/**
 * A many-to-many collection contains the members of its own source's
 * collection, from either side of the association
 */
class ManyToManyCollectionTest extends TestCase
{
    /**
     * The identifiers in each source's Doctrine collection, keyed by source
     *
     * @param class-string $sourceClass
     *
     * @return array<int, int[]>
     */
    private function expected(string $sourceClass, string $getter): array
    {
        $expected = [];
        foreach ($this->getEntityManager()->getRepository($sourceClass)->findAll() as $source) {
            $ids = array_map(static fn (object $member): int => $member->getId(), $source->$getter()->toArray());
            sort($ids);
            $expected[$source->getId()] = $ids;
        }

        $this->getEntityManager()->clear();

        return $expected;
    }

    /**
     * The identifiers in each source's GraphQL connection, keyed by source
     *
     * @return array<int, int[]>
     */
    private function resolved(string $field, string $association): array
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'user' => $driver->completeConnection(User::class),
                    'recording' => $driver->completeConnection(Recording::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ ' . $field . ' { edges { node { id ' . $association . ' { totalCount edges { node { id } } } } } } }',
        )->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        $resolved = [];
        foreach ($result['data'][$field]['edges'] as $edge) {
            $ids = array_column(array_column($edge['node'][$association]['edges'], 'node'), 'id');
            sort($ids);
            $this->assertCount($edge['node'][$association]['totalCount'], $ids);
            $resolved[$edge['node']['id']] = $ids;
        }

        return $resolved;
    }

    public function testOwningSide(): void
    {
        $this->assertEquals($this->expected(User::class, 'getRecordings'), $this->resolved('user', 'recordings'));
    }

    public function testInverseSide(): void
    {
        $this->assertEquals($this->expected(Recording::class, 'getUsers'), $this->resolved('recording', 'users'));
    }
}
