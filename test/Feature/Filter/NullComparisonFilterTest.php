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
use function array_unique;
use function sprintf;

/**
 * A comparison to null matches nothing, so eq, neq, in, notin and between
 * given null are an error; isnull matches null values
 */
class NullComparisonFilterTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function nullProvider(): array
    {
        $isnull = "  Use the 'isnull' filter to match null values.";

        return [
            'eq' => ['venue: { eq: null }', "Filter 'eq' of field 'venue' cannot be null." . $isnull],
            'neq' => ['venue: { neq: null }', "Filter 'neq' of field 'venue' cannot be null." . $isnull],
            'in' => ['venue: { in: null }', "Filter 'in' of field 'venue' cannot be null." . $isnull],
            'notin' => ['venue: { notin: null }', "Filter 'notin' of field 'venue' cannot be null." . $isnull],
            'in with a null' => [
                'venue: { in: ["Delta Center", null] }',
                "Filter 'in' of field 'venue' cannot contain null." . $isnull,
            ],
            'notin with a null' => ['venue: { notin: [null] }', "Filter 'notin' of field 'venue' cannot contain null." . $isnull],
            'between from null' => [
                'id: { between: { from: null, to: 3 } }',
                "Filter 'between' of field 'id' cannot contain null." . $isnull,
            ],
            'between to null' => [
                'id: { between: { from: 1, to: null } }',
                "Filter 'between' of field 'id' cannot contain null." . $isnull,
            ],
            'between without to' => ['id: { between: { from: 1 } }', "Filter 'between' of field 'id' cannot contain null." . $isnull],
        ];
    }

    #[DataProvider('nullProvider')]
    public function testConnectionFilter(string $filter, string $message): void
    {
        $result = $this->execute(
            new Config(),
            sprintf('{ performance(filter: { %s }) { edges { node { id } } } }', $filter),
        );

        $this->assertSame($message, $result['errors'][0]['message']);
    }

    #[DataProvider('nullProvider')]
    public function testCollectionFilter(string $filter, string $message): void
    {
        $query = sprintf(
            '{ artist { edges { node { performances(filter: { %s }) { edges { node { id } } } } } } }',
            $filter,
        );

        foreach ([true, false] as $batchAssociations) {
            $result = $this->execute(new Config(['batchAssociations' => $batchAssociations]), $query);

            $this->assertSame([$message], array_unique(array_column($result['errors'], 'message')));
        }
    }

    public function testNullVariable(): void
    {
        $result = $this->execute(
            new Config(),
            'query ($venue: String) { performance(filter: { venue: { eq: $venue } }) { edges { node { id } } } }',
            ['venue' => null],
        );

        $this->assertSame(
            "Filter 'eq' of field 'venue' cannot be null.  Use the 'isnull' filter to match null values.",
            $result['errors'][0]['message'],
        );
    }

    /** @return array<string, array{string, int}> */
    public static function valueProvider(): array
    {
        return [
            'in' => ['venue: { in: ["Delta Center"] }', 1],
            'notin' => ['venue: { notin: ["Delta Center"] }', 8],
            'between' => ['id: { between: { from: 1, to: 3 } }', 3],
            'isnull' => ['venue: { isnull: true }', 1],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testFiltersWithoutNull(string $filter, int $count): void
    {
        $result = $this->execute(
            new Config(),
            sprintf('{ performance(filter: { %s }) { edges { node { id } } } }', $filter),
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount($count, $result['data']['performance']['edges']);
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

        return GraphQL::executeQuery($schema, $query, variableValues: $variables)->toArray();
    }
}
