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
 * A filter given null is not applied
 */
class NullFilterTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function filterProvider(): array
    {
        return [
            'isnull' => ['venue: { isnull: null }'],
            'contains' => ['venue: { contains: null }'],
            'startswith' => ['venue: { startswith: null }'],
            'endswith' => ['venue: { endswith: null }'],
            'sort' => ['venue: { sort: null }'],
            'sortPriority' => ['venue: { sortPriority: null }'],
            'between' => ['performanceDate: { between: null }'],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testConnectionFilter(string $filter): void
    {
        $result = $this->execute(
            new Config(),
            sprintf('{ performance(filter: { %s }) { edges { node { id } } } }', $filter),
        );

        $this->assertCount(10, $result['data']['performance']['edges']);
    }

    #[DataProvider('filterProvider')]
    public function testCollectionFilter(string $filter): void
    {
        $query = sprintf(
            '{ artist { edges { node { performances(filter: { %s }) { edges { node { id } } } } } } }',
            $filter,
        );

        foreach ([true, false] as $batchAssociations) {
            $result = $this->execute(new Config(['batchAssociations' => $batchAssociations]), $query);

            $counts = [];
            foreach ($result['data']['artist']['edges'] as $artist) {
                $counts[] = count($artist['node']['performances']['edges']);
            }

            $this->assertSame(10, array_sum($counts));
        }
    }

    /**
     * An optional variable which is null does not filter
     */
    public function testNullVariable(): void
    {
        $result = $this->execute(
            new Config(),
            'query ($isnull: Boolean) { performance(filter: { venue: { isnull: $isnull } }) { edges { node { id } } } }',
            ['isnull' => null],
        );

        $this->assertCount(10, $result['data']['performance']['edges']);
    }

    /**
     * Other filters of the field are still applied
     */
    public function testOtherFiltersAreApplied(): void
    {
        $result = $this->execute(
            new Config(),
            '{ performance(filter: { venue: { isnull: null contains: "Center" } }) { edges { node { venue } } } }',
        );

        $this->assertSame(
            [['node' => ['venue' => 'Delta Center']], ['node' => ['venue' => 'E Center']], ['node' => ['venue' => 'E Center']]],
            $result['data']['performance']['edges'],
        );
    }

    /**
     * @param array<string, mixed>|null $variables
     *
     * @return mixed[]
     */
    private function execute(Config $config, string $query, array|null $variables = null): array
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

        $result = GraphQL::executeQuery($schema, $query, variableValues: $variables)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result;
    }
}
