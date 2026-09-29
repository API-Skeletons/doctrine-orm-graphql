<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Pagination;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function base64_encode;
use function sprintf;

/**
 * The rows of a connection are counted only when the page needs it
 */
class CountQueryTest extends QueryCountingTestCase
{
    private const string PAGE = 'pageInfo { hasNextPage hasPreviousPage startCursor endCursor } edges { cursor node { id } }';

    /** @return array{mixed[], int} The result and the number of queries */
    private function execute(string $query, bool $batchAssociations = true): array
    {
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['batchAssociations' => $batchAssociations]));

        $tableName    = $this->getEntityManager()->getClassMetadata(Performance::class)->getTableName();
        $queryBuilder = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($tableName)
            ->orderBy('id');

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => $driver->completeConnection(Artist::class),
                    'performance' => $driver->completeConnection(Performance::class),
                    'performanceRows' => $driver->dbalCompleteConnection(
                        new ObjectType(['name' => 'performanceRow', 'fields' => ['id' => Type::int()]]),
                        $queryBuilder,
                    ),
                ],
            ]),
        ]);

        $this->resetQueries();
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return [$result, $this->queryCount()];
    }

    /** @return array<string, array{string}> */
    public static function forwardProvider(): array
    {
        return [
            'no arguments' => [''],
            'first' => ['(first: 3)'],
            'first of all' => ['(first: 10)'],
            'first beyond the end' => ['(first: 50)'],
            'after' => [sprintf('(after: "%s")', base64_encode('7'))],
            'first after' => [sprintf('(first: 2, after: "%s")', base64_encode('3'))],
            'after the end' => [sprintf('(first: 2, after: "%s")', base64_encode('20'))],
            'after the last' => [sprintf('(after: "%s")', base64_encode('9'))],
        ];
    }

    /**
     * A forward page is the same whether or not totalCount is requested,
     * and without it the rows are not counted
     */
    #[DataProvider('forwardProvider')]
    public function testForwardPageIsNotCounted(string $arguments): void
    {
        foreach (['performance', 'performanceRows'] as $field) {
            [$counted, $countedQueries] = $this->execute(sprintf('{ %s%s { totalCount %s } }', $field, $arguments, self::PAGE));
            [$notCounted, $queries]     = $this->execute(sprintf('{ %s%s { %s } }', $field, $arguments, self::PAGE));

            unset($counted['data'][$field]['totalCount']);
            $this->assertSame($counted, $notCounted, $field);

            // A count and a page, or only the count when it shows the page is
            // empty; without totalCount, only the page
            $this->assertContains($countedQueries, [1, 2], $field);
            $this->assertSame(1, $queries, $field);
        }
    }

    /**
     * A collection resolved per source is not counted either
     */
    #[DataProvider('forwardProvider')]
    public function testCollectionPageIsNotCounted(string $arguments): void
    {
        $query = '{ artist { edges { node { performances%s { %s %s } } } } }';

        [$counted]              = $this->execute(sprintf($query, $arguments, 'totalCount', self::PAGE), false);
        [$notCounted, $queries] = $this->execute(sprintf($query, $arguments, '', self::PAGE), false);

        foreach ($counted['data']['artist']['edges'] as $index => $edge) {
            unset($counted['data']['artist']['edges'][$index]['node']['performances']['totalCount']);
        }

        $this->assertSame($counted, $notCounted);
        // The artists, then each artist's performances
        $this->assertSame(1 + 4, $queries);
    }

    /** @return array<string, array{string}> */
    public static function countedProvider(): array
    {
        return [
            'last' => ['(last: 2)'],
            'before' => [sprintf('(before: "%s")', base64_encode('5'))],
            'first zero' => ['(first: 0)'],
        ];
    }

    /**
     * A backward page, and first: 0, which has no rows to show whether there
     * are more, are counted
     */
    #[DataProvider('countedProvider')]
    public function testPageWhichNeedsTheCountIsCounted(string $arguments): void
    {
        [$result, $queries] = $this->execute(sprintf('{ performance%s { %s } }', $arguments, self::PAGE));

        $this->assertArrayHasKey('performance', $result['data']);
        $this->assertSame($arguments === '(first: 0)' ? 1 : 2, $queries);
    }
}
