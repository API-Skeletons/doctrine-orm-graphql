<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionLabel;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField\ComputedExpressionData;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_fill;
use function array_keys;
use function assert;
use function count;
use function implode;
use function is_array;
use function json_encode;
use function str_repeat;

/**
 * A filter's _or matches a row which matches any of its branches
 */
class OrFilterTest extends TestCase
{
    use ComputedExpressionData;

    public function setUp(): void
    {
        parent::setUp();

        $this->populateComputedExpressionData($this->getEntityManager());
    }

    /** @param array<string, mixed> $config */
    private function getDriver(array $config = []): Driver
    {
        return new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'ComputedExpression'] + $config),
        );
    }

    /** @return array<array-key, mixed> */
    private function execute(Driver $driver, string $query): array
    {
        $this->getEntityManager()->clear();

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(ComputedExpressionArtist::class),
                    'labels' => $driver->completeConnection(ComputedExpressionLabel::class),
                ],
            ]),
        ]);

        return GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    /**
     * The names of the artists a filter selects, in order, and their count
     *
     * @param array<string, mixed> $config
     *
     * @return array{int, list<string>}
     */
    private function artists(string $filter, array $config = []): array
    {
        $result = $this->execute(
            $this->getDriver($config),
            '{ artists (filter: ' . $filter . ') { totalCount edges { node { name } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result, $filter . json_encode($result['errors'] ?? null));

        return [
            $result['data']['artists']['totalCount'],
            array_column(array_column($result['data']['artists']['edges'], 'node'), 'name'),
        ];
    }

    /** @return array<string, array{string, list<string>}> */
    public static function filterProvider(): array
    {
        $all = ['Phish', 'Ween', 'moe.', "Gov't Mule", 'Grateful Dead'];

        return [
            'two branches' => ['{ _or: [{ name: { eq: "Phish" } }, { name: { eq: "Ween" } }] }', ['Phish', 'Ween']],
            'one branch' => ['{ _or: [{ name: { eq: "Phish" } }] }', ['Phish']],
            'with a field' => [
                '{ id: { gt: 1 }, _or: [{ name: { startswith: "G" } }, { recordingCount: { gt: 3 } }] }',
                ["Gov't Mule", 'Grateful Dead'],
            ],
            'a branch of two fields' => [
                '{ _or: [{ name: { startswith: "G" }, recordingCount: { gt: 2 } }, { name: { eq: "moe." } }] }',
                ['moe.', "Gov't Mule"],
            ],
            'a branch of two filters on a field' => [
                '{ _or: [{ id: { gt: 1, lt: 3 } }, { name: { eq: "Grateful Dead" } }] }',
                ['Ween', 'Grateful Dead'],
            ],
            'nested' => [
                '{ _or: [{ _or: [{ name: { eq: "Phish" } }, { name: { eq: "Ween" } }] }, '
                    . '{ recordingCount: { eq: 1 } }] }',
                ['Phish', 'Ween', 'Grateful Dead'],
            ],
            'every filter' => [
                '{ _or: [{ id: { in: [1, 2] } }, { id: { notin: [1, 2, 3, 5] } }, { id: { between: { from: 5, to: 9 } } }, '
                    . '{ name: { endswith: "Phish", isnull: true } }, { name: { contains: "e.", neq: "Ween" } }] }',
                ['Phish', 'Ween', 'moe.', "Gov't Mule", 'Grateful Dead'],
            ],
            'a computed field' => [
                '{ _or: [{ recordingCount: { between: { from: 2, to: 2 } } }, { upperName: { startswith: "MOE" } }] }',
                ['Ween', 'moe.'],
            ],
            'a computed field with arguments' => [
                '{ _or: [{ recordingCountSince: { args: { year: 2004 }, gt: 0 } }, { name: { eq: "moe." } }] }',
                ['Phish', 'Ween', 'moe.', "Gov't Mule"],
            ],
            // A branch with no condition matches every row
            'an empty branch' => ['{ _or: [{ name: { eq: "Phish" } }, {}] }', $all],
            'a branch whose filter is given null' => ['{ _or: [{ name: { eq: "Phish" } }, { name: { contains: null } }] }', $all],
            'a branch whose field is given null' => ['{ _or: [{ name: { eq: "Phish" } }, { name: null }] }', $all],
            'a branch whose _or is given null' => ['{ _or: [{ name: { eq: "Phish" } }, { _or: null }] }', $all],
            // An _or of no branches matches no row
            'no branches' => ['{ _or: [] }', []],
            'null' => ['{ _or: null }', $all],
            'a field given null' => ['{ name: null }', $all],
        ];
    }

    /** @param list<string> $names */
    #[DataProvider('filterProvider')]
    public function testFilter(string $filter, array $names): void
    {
        $this->assertSame([count($names), $names], $this->artists($filter));
    }

    public function testSort(): void
    {
        $this->assertSame(
            [3, ['Phish', "Gov't Mule", 'Ween']],
            $this->artists('{ recordingCount: { sort: DESC }, _or: [{ id: { lt: 3 } }, { id: { eq: 4 } }] }'),
        );
    }

    /**
     * A branch is of a type of the same filters but the sorts, with an _or of
     * its own
     */
    public function testFilterType(): void
    {
        $filter = $this->getDriver()->filter(ComputedExpressionArtist::class);
        $branch = self::branchType($filter);

        $this->assertSame('FilterBranch_' . self::typeName(), $branch->name());
        $this->assertSame($branch, self::branchType($branch));
        $this->assertSame(array_keys($filter->getFields()), array_keys($branch->getFields()));

        foreach (['name', 'recordingCount', 'recordingCountSince'] as $fieldName) {
            $fields = $filter->getField($fieldName)->getType();
            assert($fields instanceof InputObjectType);
            $this->assertArrayHasKey('sort', $fields->getFields());

            $branchFields = $branch->getField($fieldName)->getType();
            assert($branchFields instanceof InputObjectType);
            $this->assertArrayNotHasKey('sort', $branchFields->getFields());
            $this->assertArrayNotHasKey('sortPriority', $branchFields->getFields());
        }

        // A computed field's arguments are the same in a branch
        $since = $branch->getField('recordingCountSince')->getType();
        assert($since instanceof InputObjectType);
        $args = $since->getField('args')->getType();
        assert($args instanceof NonNull);
        $this->assertSame('FilterArgs_' . self::typeName() . '_recordingCountSince', $args->getWrappedType()->name());
    }

    public function testSortInBranchIsInvalid(): void
    {
        $result = $this->execute(
            $this->getDriver(),
            '{ artists (filter: { _or: [{ name: { sort: ASC } }] }) { edges { node { name } } } }',
        );

        $this->assertStringContainsString('"sort"', $result['errors'][0]['message']);
    }

    /** The type of the branches of a filter's _or */
    private static function branchType(InputObjectType $filter): InputObjectType
    {
        $or = $filter->getField('_or')->getType();
        assert($or instanceof ListOfType);
        $branch = $or->getWrappedType();
        assert($branch instanceof NonNull);
        $type = $branch->getWrappedType();
        assert($type instanceof InputObjectType);

        return $type;
    }

    private static function typeName(): string
    {
        return 'ApiSkeletonsTest_Doctrine_ORM_GraphQL_Entity_ComputedExpressionArtist_ComputedExpression';
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function collectionProvider(): array
    {
        return [
            'batched' => [[]],
            'over the batch limit' => [['batchLimit' => 1]],
            'not batched' => [['batchAssociations' => false]],
        ];
    }

    /**
     * A collection's filter has an _or, however the collection is fetched
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('collectionProvider')]
    public function testCollection(array $config): void
    {
        $result = $this->execute(
            $this->getDriver($config),
            '{ labels { edges { node { name artists (filter: { _or: [{ name: { startswith: "G" } }, '
            . '{ recordingCountSince: { args: { year: 2004 }, gt: 0 } }] }) { totalCount edges { node { name } } } } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result, json_encode($result['errors'] ?? null));

        $labels = [];
        foreach ($result['data']['labels']['edges'] as $label) {
            $labels[$label['node']['name']] = [
                $label['node']['artists']['totalCount'],
                array_column(array_column($label['node']['artists']['edges'], 'node'), 'name'),
            ];
        }

        $this->assertSame(
            [
                'Elektra' => [2, ['Phish', 'Ween']],
                'Relix' => [2, ["Gov't Mule", 'Grateful Dead']],
            ],
            $labels,
        );
    }

    /** A filter whose _or nests to a depth */
    private static function nested(int $depth): string
    {
        return str_repeat('{ _or: [', $depth) . '{ name: { eq: "Phish" } }' . str_repeat('] }', $depth);
    }

    /** A filter whose _or has a number of conditions */
    private static function conditions(int $count): string
    {
        return '{ _or: [' . implode(', ', array_fill(0, $count, '{ name: { eq: "Phish" } }')) . '] }';
    }

    /** @return array<string, array{string, array<string, mixed>, string|null}> */
    public static function limitProvider(): array
    {
        $depth      = "A filter may nest '_or' at most 3 deep.";
        $conditions = "A filter may have at most 100 conditions in '_or'.";

        return [
            'the default depth' => [self::nested(3), [], null],
            'beyond the default depth' => [self::nested(4), [], $depth],
            'beyond a depth' => [self::nested(2), ['filterDepth' => 1], "A filter may nest '_or' at most 1 deep."],
            'an unlimited depth' => [self::nested(10), ['filterDepth' => null], null],
            'a depth of 0' => [self::nested(10), ['filterDepth' => 0], null],
            'the default conditions' => [self::conditions(100), [], null],
            'beyond the default conditions' => [self::conditions(101), [], $conditions],
            'beyond conditions' => [
                '{ _or: [{ name: { eq: "Phish", neq: "Ween" } }, { id: { eq: 2 } }] }',
                ['filterConditions' => 2],
                "A filter may have at most 2 conditions in '_or'.",
            ],
            'conditions in nested branches' => [
                '{ _or: [{ _or: [{ id: { eq: 1 } }, { id: { eq: 2 } }] }, { id: { eq: 3 } }] }',
                ['filterConditions' => 2],
                "A filter may have at most 2 conditions in '_or'.",
            ],
            'unlimited conditions' => [self::conditions(101), ['filterConditions' => null], null],
            'conditions of 0' => [self::conditions(101), ['filterConditions' => 0], null],
            // Neither the args, nor a filter given null, nor a field given null, is a condition
            'not conditions' => [
                '{ _or: [{ recordingCountSince: { args: { year: 2004 }, gt: 0 }, name: { contains: null }, id: null }] }',
                ['filterConditions' => 1],
                null,
            ],
            // Conditions outside _or are not counted
            'outside _or' => [
                '{ id: { gt: 0, lt: 9 }, name: { neq: "x" }, _or: [{ id: { eq: 1 } }] }',
                ['filterConditions' => 1],
                null,
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('limitProvider')]
    public function testLimits(string $filter, array $config, string|null $message): void
    {
        $result = $this->execute(
            $this->getDriver($config),
            '{ artists (filter: ' . $filter . ') { edges { node { name } } } }',
        );

        if ($message === null) {
            $this->assertArrayNotHasKey('errors', $result, json_encode($result['errors'] ?? null));

            return;
        }

        // The error is the client's
        $this->assertSame($message, $result['errors'][0]['message']);
    }

    /**
     * A collection's filter is limited too
     */
    public function testCollectionLimits(): void
    {
        $result = $this->execute(
            $this->getDriver(),
            '{ labels { edges { node { artists (filter: ' . self::nested(4) . ') { edges { node { name } } } } } } }',
        );

        $this->assertSame("A filter may nest '_or' at most 3 deep.", $result['errors'][0]['message']);
    }

    public function testConfig(): void
    {
        $config = new Config();
        $this->assertSame(3, $config->getFilterDepth());
        $this->assertSame(100, $config->getFilterConditions());

        $config = new Config(['filterDepth' => 0, 'filterConditions' => 0]);
        $this->assertNull($config->getFilterDepth());
        $this->assertNull($config->getFilterConditions());

        $config = ConfigBuilder::create()->withFilterDepth(5)->withFilterConditions(null)->build();
        $this->assertSame(5, $config->getFilterDepth());
        $this->assertNull($config->getFilterConditions());
    }

    /** @return array<string, array{string}> */
    public static function negativeLimitProvider(): array
    {
        return [
            'filterDepth' => ['filterDepth'],
            'filterConditions' => ['filterConditions'],
        ];
    }

    #[DataProvider('negativeLimitProvider')]
    public function testNegativeLimit(string $option): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration value for ' . $option . ': it must be at least 0, got -1.');

        new Config([$option => -1]);
    }

    /** @return array<string, array{string, string}> */
    public static function reservedNameProvider(): array
    {
        return [
            'a field' => ['fields', 'The field name aliased as "_or"'],
            'a computed field' => ['computedFields', 'The computed field _or'],
        ];
    }

    /**
     * No field may be named _or, the name of a filter's branches
     */
    #[DataProvider('reservedNameProvider')]
    public function testReservedName(string $kind, string $message): void
    {
        $driver = $this->getDriver();
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event) use ($kind): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedExpressionArtist::class];
                assert(is_array($artist) && is_array($artist['fields']) && is_array($artist['computedFields']));

                if ($kind === 'fields') {
                    assert(is_array($artist['fields']['name']));
                    $artist['fields']['name']['alias'] = '_or';
                } else {
                    $artist['computedFields']['_or'] = $artist['computedFields']['displayName'];
                    unset($artist['computedFields']['displayName']);
                }

                $metadata[ComputedExpressionArtist::class] = $artist;
            },
        );

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message . ' of entity ' . ComputedExpressionArtist::class . ' is named "_or"');

        $driver->filter(ComputedExpressionArtist::class);
    }
}
