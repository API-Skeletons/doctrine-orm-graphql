<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_sum;
use function count;
use function sprintf;

/**
 * No value is in an empty list, and every value is not in it
 */
class EmptyListFilterTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function filterProvider(): array
    {
        return [
            'in' => ['venue: { in: [] }', 0],
            'notin' => ['venue: { notin: [] }', 10],
            'notin of an identifier' => ['id: { notin: [] }', 10],
            'notin with another filter' => ['venue: { notin: [] contains: "Center" }', 3],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testConnectionFilter(string $filter, int $count): void
    {
        $result = $this->execute(
            new Config(),
            sprintf('{ performance(filter: { %s }) { edges { node { id } } } }', $filter),
        );

        $this->assertCount($count, $result['data']['performance']['edges']);
    }

    #[DataProvider('filterProvider')]
    public function testCollectionFilter(string $filter, int $count): void
    {
        $query = sprintf(
            '{ artist { edges { node { performances(filter: { %s }) { totalCount edges { node { id } } } } } } }',
            $filter,
        );

        foreach ([true, false] as $batchAssociations) {
            $result = $this->execute(new Config(['batchAssociations' => $batchAssociations]), $query);

            $counts = $totals = [];
            foreach ($result['data']['artist']['edges'] as $artist) {
                $counts[] = count($artist['node']['performances']['edges']);
                $totals[] = $artist['node']['performances']['totalCount'];
            }

            $this->assertSame($count, array_sum($counts));
            $this->assertSame($count, array_sum($totals));
        }
    }

    /** @return mixed[] */
    private function execute(Config $config, string $query): array
    {
        $driver = new Driver($this->getEntityManager(), $config);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => $driver->completeConnection(Artist::class),
                    'performance' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result;
    }
}
