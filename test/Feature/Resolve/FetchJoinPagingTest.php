<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\AST\PartialObjectExpression;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function class_exists;

/**
 * A QueryBuilder event listener which fetch joins a collection gives an
 * entity a row for each member; a page is still of entities, not rows
 */
class FetchJoinPagingTest extends TestCase
{
    private const string PAGE = 'pageInfo { hasNextPage hasPreviousPage } edges { cursor node { id } }';

    /** @return mixed[] */
    private function artists(string|null $select, string $arguments, string $selection): array
    {
        $driver = new Driver($this->getEntityManager());

        if ($select !== null) {
            $driver->service(EventDispatcher::class)->subscribeTo(
                'artist.fetchJoin',
                static function (QueryBuilderEvent $event) use ($select): void {
                    $event->getQueryBuilder()->leftJoin('entity.performances', 'p')->addSelect($select);
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

        // Partial objects are not supported by ORM 3.0 to 3.2
        if (! class_exists(PartialObjectExpression::class)) {
            return;
        }

        $this->assertSame($expected, $this->artists('partial p.{id, venue}', $arguments, $selection));
    }

    /**
     * A collection whose association has an event name is not batched, and a
     * listener may fetch join its targets' collections too
     */
    public function testFetchJoinedCollectionPageIsOfEntities(): void
    {
        $query = '{ artist(filter: { name: { eq: "Grateful Dead" } }) { edges { node { '
            . 'performances(first: 2) { totalCount pageInfo { hasNextPage } edges { node { id } } } } } } }';

        $execute = function (bool $fetchJoin) use ($query): array {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent']));

            if ($fetchJoin) {
                // The first performance has several recordings
                $driver->service(EventDispatcher::class)->subscribeTo(
                    Artist::class . '.performances.criteria',
                    static function (QueryBuilderEvent $event): void {
                        $event->getQueryBuilder()->leftJoin('entity.recordings', 'r')->addSelect('r');
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
}
