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

use function array_column;
use function array_merge;
use function sort;
use function sprintf;

/**
 * contains, startswith and endswith match their value literally; LIKE
 * wildcards in it are not wildcards
 */
class LikeFilterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $connection = $this->getEntityManager()->getConnection();
        foreach ([1 => '100% Arena', 2 => 'Big_Top', 3 => 'Wow! Hall'] as $id => $venue) {
            $connection->executeStatement('UPDATE performance SET venue = ? WHERE id = ?', [$venue, $id]);
        }
    }

    /** @return array<string, array{string, string, string[]}> */
    public static function filterProvider(): array
    {
        return [
            'contains percent' => ['contains', '%', ['100% Arena']],
            'contains underscore' => ['contains', '_', ['Big_Top']],
            'contains escape character' => ['contains', '!', ['Wow! Hall']],
            'contains escaped sequence' => ['contains', '!%', []],
            'contains text' => ['contains', 'Arena', ['100% Arena']],
            'starts with percent' => ['startswith', '%', []],
            'starts with value and percent' => ['startswith', '100%', ['100% Arena']],
            'starts with underscore' => ['startswith', 'B_g', []],
            'ends with underscore' => ['endswith', '_Top', ['Big_Top']],
            'ends with percent' => ['endswith', '%', []],
        ];
    }

    /** @param string[] $venues */
    #[DataProvider('filterProvider')]
    public function testConnectionFilter(string $filter, string $value, array $venues): void
    {
        $query = sprintf(
            '{ performance(filter: { venue: { %s: "%s" } }) { edges { node { venue } } } }',
            $filter,
            $value,
        );

        $result = $this->execute(new Config(), $query);

        $this->assertSame($venues, $this->venues($result['data']['performance']['edges']));
    }

    /** @param string[] $venues */
    #[DataProvider('filterProvider')]
    public function testCollectionFilter(string $filter, string $value, array $venues): void
    {
        $query = sprintf(
            '{ artist { edges { node { performances(filter: { venue: { %s: "%s" } }) { edges { node { venue } } } } } } }',
            $filter,
            $value,
        );

        foreach ([true, false] as $batchAssociations) {
            $result = $this->execute(new Config(['batchAssociations' => $batchAssociations]), $query);

            $edges = [];
            foreach ($result['data']['artist']['edges'] as $artist) {
                $edges = array_merge($edges, $artist['node']['performances']['edges']);
            }

            $found = $this->venues($edges);
            sort($found);

            $this->assertSame($venues, $found);
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

    /**
     * @param mixed[] $edges
     *
     * @return string[]
     */
    private function venues(array $edges): array
    {
        return array_column(array_column($edges, 'node'), 'venue');
    }
}
