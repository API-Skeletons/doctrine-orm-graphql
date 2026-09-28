<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_column;
use function strtoupper;

/**
 * A computed field is computed only when it is queried, once for each entity
 */
class LazyComputedFieldTest extends TestCase
{
    private int $calls = 0;

    private Schema $schema;

    private function createSchema(bool $useHydratorCache = false): void
    {
        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'computedFieldTest', 'useHydratorCache' => $useHydratorCache]),
        );

        // Count the calls of the computed field
        $hydrator = $driver->get(HydratorContainer::class)->get(Artist::class);
        $this->assertInstanceOf(DoctrineObjectWithComputed::class, $hydrator);
        $hydrator->addComputedField('fullName', function (Artist $artist): string {
            $this->calls++;

            return $artist->getFullName();
        });

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artist' => $driver->completeConnection(Artist::class)],
            ]),
        ]);
    }

    /** @return mixed[] */
    private function execute(string $query): array
    {
        $result = GraphQL::executeQuery($this->schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return array_column($result['data']['artist']['edges'], 'node');
    }

    public function testNotComputedWhenNotQueried(): void
    {
        $this->createSchema();

        $nodes = $this->execute('{ artist { edges { node { id name } } } }');

        $this->assertCount(4, $nodes);
        $this->assertSame(0, $this->calls);
    }

    public function testComputedOnceForEachEntity(): void
    {
        $this->createSchema();

        $nodes = $this->execute('{ artist { edges { node { name fullName again: fullName } } } }');

        $this->assertCount(4, $nodes);
        $this->assertSame(4, $this->calls);

        foreach ($nodes as $node) {
            $this->assertSame(strtoupper($node['name']), $node['fullName']);
            $this->assertSame($node['fullName'], $node['again']);
        }
    }

    /**
     * A field queried before the extract of the entity is computed too
     */
    public function testComputedFieldQueriedFirst(): void
    {
        $this->createSchema();

        $nodes = $this->execute('{ artist { edges { node { fullName name } } } }');

        $this->assertSame(4, $this->calls);
        $this->assertSame(strtoupper($nodes[0]['name']), $nodes[0]['fullName']);
    }

    /**
     * With the hydrator cache, a computed value is cached with the entity's
     * other values
     */
    public function testComputedValueIsCachedWithTheHydratorCache(): void
    {
        $this->createSchema(true);

        $this->execute('{ artist { edges { node { name } } } }');
        $this->assertSame(0, $this->calls);

        $this->execute('{ artist { edges { node { fullName } } } }');
        $this->execute('{ artist { edges { node { fullName } } } }');
        $this->assertSame(4, $this->calls);
    }
}
