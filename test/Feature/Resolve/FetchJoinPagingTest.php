<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestOrderedItem;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestOrderedOwner;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\AST\PartialObjectExpression;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function class_exists;

/**
 * A QueryBuilder event listener which joins a collection, fetched or not,
 * gives an entity a row for each member; a page is still of entities, not
 * rows
 */
class FetchJoinPagingTest extends TestCase
{
    private const string PAGE = 'pageInfo { hasNextPage hasPreviousPage } edges { cursor node { id } }';

    /**
     * @param string|null $select What the listener's join selects: null for no join, an empty string for a join
     *                            which is not fetched
     *
     * @return mixed[]
     */
    private function artists(string|null $select, string $arguments, string $selection): array
    {
        $driver = new Driver($this->getEntityManager());

        if ($select !== null) {
            $driver->service(EventDispatcher::class)->subscribeTo(
                'artist.fetchJoin',
                static function (QueryBuilderEvent $event) use ($select): void {
                    $event->getQueryBuilder()->leftJoin('entity.performances', 'p');

                    if ($select === '') {
                        return;
                    }

                    $event->getQueryBuilder()->addSelect($select);
                },
            );
        }

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => ['filter' => $driver->filter(Artist::class)] + $driver->pagination(),
                        'resolve' => $driver->resolve(Artist::class, 'artist.fetchJoin'),
                    ],
                ],
            ]),
        ]);

        $this->getEntityManager()->clear();
        $result = GraphQL::executeQuery($schema, '{ artists' . $arguments . ' { ' . $selection . ' } }')->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data'];
    }

    /** @return array<string, array{string, string}> */
    public static function pageProvider(): array
    {
        // Grateful Dead, the first artist, has five performances
        return [
            'first' => ['(first: 2)', 'totalCount ' . self::PAGE],
            'first without totalCount' => ['(first: 2)', self::PAGE],
            'first after' => ['(first: 2, after: "MA==")', self::PAGE],
            'last' => ['(last: 2)', 'totalCount ' . self::PAGE],
            'every artist' => ['', 'totalCount ' . self::PAGE],
        ];
    }

    #[DataProvider('pageProvider')]
    public function testFetchJoinedPageIsOfEntities(string $arguments, string $selection): void
    {
        $expected = $this->artists(null, $arguments, $selection);

        $this->assertSame($expected, $this->artists('p', $arguments, $selection));
        $this->assertSame($expected, $this->artists('', $arguments, $selection));

        // Partial objects are not supported by ORM 3.0 to 3.2
        if (! class_exists(PartialObjectExpression::class)) {
            return;
        }

        $this->assertSame($expected, $this->artists('partial p.{id, venue}', $arguments, $selection));
    }

    /** @return array<string, array{bool}> */
    public static function fetchProvider(): array
    {
        return [
            'fetch join' => [true],
            'join' => [false],
        ];
    }

    /**
     * A collection whose association has an event name is not batched, and a
     * listener may join its targets' collections too
     */
    #[DataProvider('fetchProvider')]
    public function testJoinedCollectionPageIsOfEntities(bool $fetch): void
    {
        $query = '{ artist(filter: { name: { eq: "Grateful Dead" } }) { edges { node { '
            . 'performances(first: 2) { totalCount pageInfo { hasNextPage } edges { node { id } } } } } } }';

        $execute = function (bool $join) use ($query, $fetch): array {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent']));

            if ($join) {
                // The first performance has several recordings
                $driver->service(EventDispatcher::class)->subscribeTo(
                    Artist::class . '.performances.criteria',
                    static function (QueryBuilderEvent $event) use ($fetch): void {
                        $event->getQueryBuilder()->leftJoin('entity.recordings', 'r');

                        if (! $fetch) {
                            return;
                        }

                        $event->getQueryBuilder()->addSelect('r');
                    },
                );
            }

            $schema = new Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => ['artist' => $driver->completeConnection(Artist::class)],
                ]),
            ]);

            $this->getEntityManager()->clear();
            $result = GraphQL::executeQuery($schema, $query)->toArray();
            $this->assertArrayNotHasKey('errors', $result);

            return $result['data'];
        };

        $expected = $execute(false);
        $this->assertCount(2, $expected['artist']['edges'][0]['node']['performances']['edges']);
        $this->assertSame($expected, $execute(true));
    }

    /**
     * A many-to-many collection is joined to its source, which gives each
     * target one row and needs no Paginator.  A listener's join to entities
     * which match several rows does.
     */
    public function testJoinedManyToManyPageIsOfEntities(): void
    {
        $entityManager = $this->getEntityManager();
        $owner         = new TestOrderedOwner('Owner');
        $entityManager->persist($owner);

        foreach ([['a', 1], ['b', 1], ['c', 1], ['d', 2]] as [$name, $position]) {
            $item = new TestOrderedItem($name, $position, $owner);
            $entityManager->persist($item);
            $owner->addTag($item);
        }

        $entityManager->flush();

        $query = '{ owners { edges { node { tags(first: 2) { totalCount pageInfo { hasNextPage } edges { node { name } } } } } } }';

        $execute = function (bool $join) use ($query): array {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => 'OrderByEvent']));

            if ($join) {
                // Each item of position 1 matches three items
                $driver->service(EventDispatcher::class)->subscribeTo(
                    'orderedTags',
                    static function (QueryBuilderEvent $event): void {
                        $event->getQueryBuilder()->innerJoin(
                            TestOrderedItem::class,
                            'other',
                            'WITH',
                            'other.position = entity.position',
                        );
                    },
                );
            }

            $schema = new Schema([
                'query' => new ObjectType([
                    'name' => 'query',
                    'fields' => ['owners' => $driver->completeConnection(TestOrderedOwner::class)],
                ]),
            ]);

            $this->getEntityManager()->clear();
            $result = GraphQL::executeQuery($schema, $query)->toArray();
            $this->assertArrayNotHasKey('errors', $result);

            return $result['data'];
        };

        $expected = $execute(false);
        $this->assertSame(
            ['totalCount' => 4, 'pageInfo' => ['hasNextPage' => true], 'edges' => [['node' => ['name' => 'a']], ['node' => ['name' => 'b']]]],
            $expected['owners']['edges'][0]['node']['tags'],
        );
        $this->assertSame($expected, $execute(true));
    }
}
