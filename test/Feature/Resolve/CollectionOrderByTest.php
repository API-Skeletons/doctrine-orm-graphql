<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestOrderedItem;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestOrderedOwner;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_key_last;
use function array_map;

/**
 * A collection is ordered by its association's #[ORM\OrderBy], as Doctrine
 * orders it, after any sort filter and any ordering of a QueryBuilder
 * listener, and before the identifier
 */
class CollectionOrderByTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $first  = new TestOrderedOwner('First');
        $second = new TestOrderedOwner('Second');
        $entityManager->persist($first);
        $entityManager->persist($second);

        foreach ([['b', 2, $first], ['c', 1, $first], ['a', 1, $first], ['x', 1, $second], ['y', 1, $second]] as [$name, $position, $owner]) {
            $item = new TestOrderedItem($name, $position, $owner);
            $entityManager->persist($item);
            $owner->addTag($item);
        }

        $entityManager->flush();
        $entityManager->clear();
    }

    /** @return array<string, list<string>> The names of each owner's collection, by owner */
    private function names(Driver $driver, string $collection): array
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['owners' => $driver->completeConnection(TestOrderedOwner::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ owners { edges { node { name ' . $collection . ' { edges { node { name } } } } } } }',
        )->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        $names = [];
        foreach ($result['data']['owners']['edges'] as $edge) {
            $field                        = $edge['node'][array_key_last($edge['node'])];
            $names[$edge['node']['name']] = array_map(
                static fn (array $item): string => $item['node']['name'],
                $field['edges'],
            );
        }

        return $names;
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function configProvider(): array
    {
        return [
            'batched' => [['batchAssociations' => true]],
            'not batched' => [['batchAssociations' => false]],
            'over the batch limit' => [['batchLimit' => 0]],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('configProvider')]
    public function testCollectionsAreOrderedByTheirOrderBy(array $config): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'OrderBy'] + $config));

        // name DESC
        $this->assertSame(['First' => ['c', 'b', 'a'], 'Second' => ['y', 'x']], $this->names($driver, 'items'));

        // position ASC, name ASC
        $this->assertSame(['First' => ['a', 'c', 'b'], 'Second' => ['x', 'y']], $this->names($driver, 'tags'));
    }

    public function testDoctrineOrdersTheSame(): void
    {
        $owner = $this->getEntityManager()->getRepository(TestOrderedOwner::class)->findOneBy(['name' => 'First']);
        $this->assertInstanceOf(TestOrderedOwner::class, $owner);

        $names = static fn (iterable $items): array => array_map(
            static fn (TestOrderedItem $item): string => $item->getName(),
            [...$items],
        );

        $this->assertSame(['c', 'b', 'a'], $names($owner->getItems()));
        $this->assertSame(['a', 'c', 'b'], $names($owner->getTags()));
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('configProvider')]
    public function testSortFilterPrecedesOrderBy(array $config): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'OrderBy'] + $config));

        // position ASC, then name DESC
        $this->assertSame(
            ['First' => ['c', 'a', 'b'], 'Second' => ['y', 'x']],
            $this->names($driver, 'items(filter: { position: { sort: ASC } })'),
        );
    }

    public function testListenerOrderingPrecedesOrderBy(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'OrderByEvent']));
        $driver->get(EventDispatcher::class)->subscribeTo(
            'orderedItems',
            static function (QueryBuilderEvent $event): void {
                $event->getQueryBuilder()->addOrderBy('entity.position', 'DESC');
            },
        );

        // position DESC, then name DESC
        $this->assertSame(['First' => ['b', 'c', 'a'], 'Second' => ['y', 'x']], $this->names($driver, 'items'));
    }
}
