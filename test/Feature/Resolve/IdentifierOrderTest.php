<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;

use function array_column;
use function array_map;

/**
 * Connections are ordered by the identifier after any other ordering, so the
 * order of rows, and therefore every page, is deterministic
 */
class IdentifierOrderTest extends TestCase
{
    /**
     * The ORDER BY of a query builder
     *
     * @return string[]
     */
    private function orderBy(QueryBuilder $queryBuilder): array
    {
        return array_map(static fn (OrderBy $orderBy): string => (string) $orderBy, $queryBuilder->getDQLPart('orderBy'));
    }

    /**
     * Run a query and return the query builder its connection used, captured
     * through the QueryBuilder event
     */
    private function captureQueryBuilder(Driver $driver, string $eventName, string $query, bool $listenerSorts): QueryBuilder
    {
        $captured = null;
        $driver->get(EventDispatcher::class)->subscribeTo(
            $eventName,
            static function (QueryBuilderEvent $event) use (&$captured, $listenerSorts): void {
                $captured = $event->getQueryBuilder();

                if (! $listenerSorts) {
                    return;
                }

                $captured->addOrderBy('entity.venue', 'DESC');
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => [
                        'type' => $driver->connection(Performance::class),
                        'args' => ['filter' => $driver->filter(Performance::class)] + $driver->pagination(),
                        'resolve' => $driver->resolve(Performance::class, 'performance.querybuilder'),
                    ],
                    'artist' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);
        $this->assertInstanceOf(QueryBuilder::class, $captured);

        return $captured;
    }

    public function testTopLevelConnectionIsOrderedByIdentifier(): void
    {
        $queryBuilder = $this->captureQueryBuilder(
            new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent'])),
            'performance.querybuilder',
            '{ performance { edges { node { id } } } }',
            false,
        );

        $this->assertSame(['entity.id ASC'], $this->orderBy($queryBuilder));
    }

    public function testIdentifierBreaksTiesAfterSort(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent']));

        $queryBuilder = $this->captureQueryBuilder(
            $driver,
            'performance.querybuilder',
            '{ performance (filter: { venue: { sort: ASC } }) { edges { node { id } } } }',
            false,
        );

        $this->assertSame(['entity.venue ASC', 'entity.id ASC'], $this->orderBy($queryBuilder));
    }

    public function testListenerOrderingComesBeforeIdentifier(): void
    {
        $queryBuilder = $this->captureQueryBuilder(
            new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent'])),
            'performance.querybuilder',
            '{ performance { edges { node { id } } } }',
            true,
        );

        $this->assertSame(['entity.venue DESC', 'entity.id ASC'], $this->orderBy($queryBuilder));
    }

    public function testCollectionIsOrderedByIdentifierAfterListener(): void
    {
        $queryBuilder = $this->captureQueryBuilder(
            new Driver($this->getEntityManager(), new Config(['group' => 'CriteriaEvent'])),
            Artist::class . '.performances.criteria',
            '{ artist (filter: { name: { eq: "Phish" } }) { edges { node { performances { edges { node { id } } } } } } }',
            true,
        );

        $this->assertSame(['entity.venue DESC', 'entity.id ASC'], $this->orderBy($queryBuilder));
    }

    /**
     * Two Phish performances share a venue.  Sorted by venue, they are
     * ordered by identifier.
     */
    public function testTiedRowsAreReturnedInIdentifierOrder(): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['performance' => $driver->completeConnection(Performance::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ performance (filter: { venue: { eq: "E Center" sort: DESC } }) { edges { node { id } } } }',
        )->toArray();

        $ids = array_column(array_column($result['data']['performance']['edges'], 'node'), 'id');
        $this->assertCount(2, $ids);
        $this->assertLessThan($ids[1], $ids[0]);
    }
}
