<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestCompositeKeyReference;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;
use function array_sum;
use function count;

/**
 * A to-one association is filtered by the identifier of its target
 */
class AssociationFilterTest extends TestCase
{
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

        return GraphQL::executeQuery($schema, $query)->toArray();
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return string[]
     */
    private function filterFields(array $config): array
    {
        $filter = (new Driver($this->getEntityManager(), new Config($config)))->filter(Performance::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);
        $artist = $filter->getField('artist')->getType();
        $this->assertInstanceOf(InputObjectType::class, $artist);

        return array_keys($artist->getFields());
    }

    public function testAssociationFilters(): void
    {
        $this->assertSame(['eq', 'neq', 'in', 'notin', 'isnull'], $this->filterFields([]));
    }

    public function testExcludedFiltersAreLeftOut(): void
    {
        $this->assertSame(['eq', 'neq', 'notin'], $this->filterFields(['excludeFilters' => [Filters::IN, Filters::ISNULL]]));
    }

    /** @return array<string, array{string, int}> */
    public static function filterProvider(): array
    {
        // Artist 1 has five performances, artist 2 three and the others two
        return [
            'eq' => ['eq: 1', 5],
            'neq' => ['neq: 1', 5],
            'in' => ['in: [1, 2]', 8],
            'notin' => ['notin: [1, 2]', 2],
            'isnull' => ['isnull: true', 0],
            'isnull false' => ['isnull: false', 10],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testConnectionFilter(string $filter, int $count): void
    {
        $result = $this->execute(new Config(), '{ performance(filter: { artist: { ' . $filter . ' } }) { edges { node { id } } } }');

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount($count, $result['data']['performance']['edges']);
    }

    #[DataProvider('filterProvider')]
    public function testCollectionFilter(string $filter, int $count): void
    {
        $query = '{ artist { edges { node { performances(filter: { artist: { ' . $filter . ' } }) { edges { node { id } } } } } } }';

        foreach ([true, false] as $batchAssociations) {
            $result = $this->execute(new Config(['batchAssociations' => $batchAssociations]), $query);

            $this->assertArrayNotHasKey('errors', $result);

            $counts = [];
            foreach ($result['data']['artist']['edges'] as $artist) {
                $counts[] = count($artist['node']['performances']['edges']);
            }

            $this->assertSame($count, array_sum($counts));
        }
    }

    public function testAliasedAssociation(): void
    {
        $result = $this->execute(
            new Config(['group' => 'ExtractionMap']),
            '{ performance(filter: { band: { in: [2] } }) { edges { node { key } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(3, $result['data']['performance']['edges']);
    }

    /** @return array<string, array{string, string}> */
    public static function nonIntegerProvider(): array
    {
        return [
            'eq' => ['eq', 'eq: "abc"'],
            'neq' => ['neq', 'neq: "1.5"'],
            'in' => ['in', 'in: ["1", "x"]'],
            'notin' => ['notin', 'notin: [""]'],
        ];
    }

    /**
     * An ID may be any string.  A value which is not an integer, for an
     * integer identifier, is an error, rather than compared as text or a
     * database error.
     */
    #[DataProvider('nonIntegerProvider')]
    public function testValueOfAnIntegerIdentifierMustBeAnInteger(string $filter, string $value): void
    {
        $result = $this->execute(
            new Config(),
            '{ performance(filter: { artist: { ' . $value . ' } }) { edges { node { id } } } }',
        );

        $this->assertSame(
            "Filter '" . $filter . "' of field 'artist' must be an integer.",
            $result['errors'][0]['message'],
        );
    }

    public function testIntegerIdentifierAsAString(): void
    {
        $result = $this->execute(
            new Config(),
            '{ performance(filter: { artist: { eq: "1", in: ["1", "2"] } }) { edges { node { id } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertCount(5, $result['data']['performance']['edges']);
    }

    public function testNullComparisonIsRejected(): void
    {
        $result = $this->execute(new Config(), '{ performance(filter: { artist: { in: [1, null] } }) { edges { node { id } } } }');

        $this->assertSame(
            "Filter 'in' of field 'artist' cannot contain null.  Use the 'isnull' filter to match null values.",
            $result['errors'][0]['message'],
        );
    }

    /**
     * The identifier of an entity with a composite identifier is not one
     * value, so an association to it has no filter
     */
    public function testAssociationToACompositeIdentifierHasNoFilter(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'CompositeKeyTest']));
        $filter = $driver->filter(TestCompositeKeyReference::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);

        $this->assertNull($filter->findField('compositeKeyEntity'));
        $this->assertNotNull($filter->findField('id'));
    }
}
