<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\QueryBuilder as QueryBuilderEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionLabel;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_keys;
use function array_map;
use function assert;
use function is_array;
use function json_encode;

/**
 * A computed field with an expression is filtered and sorted by it
 */
class ComputedFieldExpressionTest extends TestCase
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

    private function getSchema(Driver $driver, string|null $eventName = null): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(ComputedExpressionArtist::class, null, $eventName),
                    'labels' => $driver->completeConnection(ComputedExpressionLabel::class),
                ],
            ]),
        ]);
    }

    /** @return array<array-key, mixed> */
    private function execute(Driver $driver, string $query, string|null $eventName = null): array
    {
        $this->getEntityManager()->clear();

        return GraphQL::executeQuery($this->getSchema($driver, $eventName), $query)
            ->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    /**
     * The names of the artists a filter selects, in order
     *
     * @return list<string>
     */
    private function artistNames(string $filter, Driver|null $driver = null): array
    {
        $result = $this->execute(
            $driver ?? $this->getDriver(),
            '{ artists (filter: ' . $filter . ') { edges { node { name } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result, $filter . json_encode($result['errors'] ?? null));

        return array_map(
            static fn (array $edge): string => $edge['node']['name'],
            $result['data']['artists']['edges'],
        );
    }

    /** @return array<string, array{string, list<string>}> */
    public static function filterProvider(): array
    {
        return [
            'gt' => ['{ recordingCount: { gt: 2 } }', ['Phish', "Gov't Mule"]],
            'eq' => ['{ recordingCount: { eq: 0 } }', ['moe.']],
            'neq' => ['{ recordingCount: { neq: 0 } }', ['Phish', 'Ween', "Gov't Mule", 'Grateful Dead']],
            'lt' => ['{ recordingCount: { lt: 2 } }', ['moe.', 'Grateful Dead']],
            'lte' => ['{ recordingCount: { lte: 2 } }', ['Ween', 'moe.', 'Grateful Dead']],
            'gte' => ['{ recordingCount: { gte: 3 } }', ['Phish', "Gov't Mule"]],
            'between' => ['{ recordingCount: { between: { from: 1, to: 2 } } }', ['Ween', 'Grateful Dead']],
            'in' => ['{ nameLength: { in: [4, 5] } }', ['Phish', 'Ween', 'moe.']],
            'notin' => ['{ nameLength: { notin: [4, 5] } }', ["Gov't Mule", 'Grateful Dead']],
            'isnull' => ['{ upperName: { isnull: false } }', ['Phish', 'Ween', 'moe.', "Gov't Mule", 'Grateful Dead']],
            'between a function' => ['{ nameLength: { between: { from: 5, to: 10 } } }', ['Phish', "Gov't Mule"]],
            'two filters on one field' => ['{ recordingCount: { gt: 0, lt: 4 } }', ['Ween', "Gov't Mule", 'Grateful Dead']],
            'a filter given null' => ['{ recordingCount: { gt: null } }', ['Phish', 'Ween', 'moe.', "Gov't Mule", 'Grateful Dead']],
            'with a field filter' => ['{ recordingCount: { gt: 0 }, name: { startswith: "G" } }', ["Gov't Mule", 'Grateful Dead']],
            'a string' => ['{ upperName: { eq: "PHISH" } }', ['Phish']],
            'contains' => ['{ upperName: { contains: "EE" } }', ['Ween']],
            'startswith' => ['{ upperName: { startswith: "GR" } }', ['Grateful Dead']],
        ];
    }

    /** @param list<string> $names */
    #[DataProvider('filterProvider')]
    public function testFilter(string $filter, array $names): void
    {
        $this->assertSame($names, $this->artistNames($filter));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function sortProvider(): array
    {
        return [
            // Ties are ordered by the identifier
            'desc' => ['{ recordingCount: { sort: DESC } }', ['Phish', "Gov't Mule", 'Ween', 'Grateful Dead', 'moe.']],
            'asc' => ['{ recordingCount: { sort: ASC } }', ['moe.', 'Grateful Dead', 'Ween', "Gov't Mule", 'Phish']],
            'with a filter on the field' => [
                '{ recordingCount: { gt: 0, sort: ASC } }',
                ['Grateful Dead', 'Ween', "Gov't Mule", 'Phish'],
            ],
            'a string' => ['{ upperName: { sort: ASC } }', ["Gov't Mule", 'Grateful Dead', 'moe.', 'Phish', 'Ween']],
            'priority before a field' => [
                '{ recordingCount: { sort: DESC, sortPriority: 1 }, name: { sort: DESC, sortPriority: 2 } }',
                ['Phish', "Gov't Mule", 'Ween', 'Grateful Dead', 'moe.'],
            ],
            'priority after a field' => [
                '{ recordingCount: { sort: DESC, sortPriority: 2 }, name: { sort: DESC, sortPriority: 1 } }',
                ['moe.', 'Ween', 'Phish', 'Grateful Dead', "Gov't Mule"],
            ],
            'two computed fields' => [
                '{ recordingCountIn: { args: { year: 2003 }, sort: DESC, sortPriority: 1 }, '
                    . 'recordingCount: { sort: ASC, sortPriority: 2 } }',
                ['Ween', "Gov't Mule", 'Phish', 'moe.', 'Grateful Dead'],
            ],
        ];
    }

    /** @param list<string> $names */
    #[DataProvider('sortProvider')]
    public function testSort(string $filter, array $names): void
    {
        $this->assertSame($names, $this->artistNames($filter));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function argumentProvider(): array
    {
        return [
            'given' => ['{ recordingCountIn: { args: { year: 2003 }, gt: 0 } }', ['Phish', 'Ween', "Gov't Mule"]],
            'given null' => [
                '{ recordingCountIn: { args: { year: null }, gt: 1 } }',
                ['Phish', 'Ween', "Gov't Mule"],
            ],
            // An argument without a default is null
            'not given' => ['{ recordingCountIn: { gt: 2 } }', ['Phish', "Gov't Mule"]],
            'args not given' => ['{ recordingCountIn: { args: {}, gt: 2 } }', ['Phish', "Gov't Mule"]],
            'required' => ['{ recordingCountSince: { args: { year: 2004 }, gt: 0 } }', ['Phish', 'Ween', "Gov't Mule"]],
            'default' => ['{ recordingCountFrom: { gt: 1 } }', ['Phish', 'Ween', "Gov't Mule"]],
            'default replaced' => ['{ recordingCountFrom: { args: { year: 2005 }, gt: 0 } }', ['Ween']],
            'a date' => [
                '{ recordingCountBefore: { args: { date: "2002-12-01" }, gt: 0 } }',
                ['Phish', "Gov't Mule", 'Grateful Dead'],
            ],
            'sorted' => [
                '{ recordingCountSince: { args: { year: 2001 }, gt: 0, sort: DESC } }',
                ['Phish', "Gov't Mule", 'Ween'],
            ],
        ];
    }

    /**
     * The filter's args are the arguments of the field's expression
     *
     * @param list<string> $names
     */
    #[DataProvider('argumentProvider')]
    public function testArguments(string $filter, array $names): void
    {
        $this->assertSame($names, $this->artistNames($filter));
    }

    public function testRequiredArgumentIsRequired(): void
    {
        $result = $this->execute(
            $this->getDriver(),
            '{ artists (filter: { recordingCountSince: { gt: 0 } }) { edges { node { name } } } }',
        );

        $this->assertStringContainsString('args', $result['errors'][0]['message']);
    }

    /**
     * The filters of a field without arguments are shared with other fields'.
     * A field with arguments has its own, with args of the arguments its
     * expression uses, required when one of them is.
     */
    public function testFilterType(): void
    {
        $fields = $this->getDriver()->filter(ComputedExpressionArtist::class)->getFields();

        $this->assertArrayNotHasKey('displayName', $fields);
        $this->assertSame($fields['id']->getType(), $fields['nameLength']->getType());

        // DQL takes a subquery for none of IN, IS NULL and LIKE
        $recordingCount = $fields['recordingCount']->getType();
        assert($recordingCount instanceof InputObjectType);
        $this->assertSame(
            ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'between', 'sort', 'sortPriority'],
            array_keys($recordingCount->getFields()),
        );

        $upperName = $fields['upperName']->getType();
        assert($upperName instanceof InputObjectType);
        $this->assertSame(
            ['eq', 'neq', 'contains', 'startswith', 'endswith', 'isnull', 'sort', 'sortPriority'],
            array_keys($upperName->getFields()),
        );

        $countIn = $fields['recordingCountIn']->getType();
        assert($countIn instanceof InputObjectType);
        $this->assertStringContainsString('_recordingCountIn_', $countIn->name());
        $args = $countIn->getField('args')->getType();
        assert($args instanceof InputObjectType);
        $this->assertSame(['year'], array_keys($args->getFields()));

        $countSince = $fields['recordingCountSince']->getType();
        assert($countSince instanceof InputObjectType);
        $this->assertInstanceOf(NonNull::class, $countSince->getField('args')->getType());

        $countFrom = $fields['recordingCountFrom']->getType();
        assert($countFrom instanceof InputObjectType);
        $this->assertInstanceOf(InputObjectType::class, $countFrom->getField('args')->getType());
    }

    /**
     * The global and the entity's excluded filters apply to a computed field
     */
    public function testExcludedFilters(): void
    {
        $fields = $this->getDriver(['excludeFilters' => [Filters::SORT]])
            ->filter(ComputedExpressionArtist::class)->getFields();

        $type = $fields['recordingCount']->getType();
        assert($type instanceof InputObjectType);
        $this->assertArrayNotHasKey('sort', $type->getFields());

        $driver = $this->getDriver();
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedExpressionArtist::class];
                assert(is_array($artist));
                $artist['excludeFilters']                  = ['gt'];
                $metadata[ComputedExpressionArtist::class] = $artist;
            },
        );

        $type = $driver->filter(ComputedExpressionArtist::class)->getFields()['recordingCount']->getType();
        assert($type instanceof InputObjectType);
        $this->assertArrayNotHasKey('gt', $type->getFields());
    }

    /**
     * A computed field all of whose filters are excluded, or of a type which
     * is not filtered, is not in the filters
     */
    public function testFieldWithoutFilters(): void
    {
        $driver = $this->getDriver();
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedExpressionArtist::class];
                assert(is_array($artist));
                $artist['computedFields']['upperName']['excludeFilters'] = Filters::toStringArray(Filters::cases());
                $artist['computedFields']['recordingCount']['type']      = 'blob';
                $artist['computedFields']['recordingCountFrom']['type']  = 'simple_array';
                $metadata[ComputedExpressionArtist::class]               = $artist;
            },
        );

        $fields = $driver->filter(ComputedExpressionArtist::class)->getFields();

        $this->assertArrayNotHasKey('upperName', $fields);
        $this->assertArrayNotHasKey('recordingCount', $fields);
        $this->assertArrayNotHasKey('recordingCountFrom', $fields);
        $this->assertArrayHasKey('recordingCountIn', $fields);
    }

    /** @return array<string, array{string}> */
    public static function paginationProvider(): array
    {
        return [
            'without arguments' => ['{ recordingCount: { gt: 0, sort: DESC } }'],
            'with arguments' => ['{ recordingCountSince: { args: { year: 1970 }, gt: 0, sort: DESC } }'],
        ];
    }

    /**
     * A page and the total count are of the filtered and sorted rows
     */
    #[DataProvider('paginationProvider')]
    public function testPagination(string $filter): void
    {
        $result = $this->execute(
            $this->getDriver(),
            '{ artists (filter: ' . $filter . ', first: 2, after: "MQ==") '
            . '{ totalCount edges { node { name } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(4, $result['data']['artists']['totalCount']);
        $this->assertSame(
            ['Ween', 'Grateful Dead'],
            array_column(array_column($result['data']['artists']['edges'], 'node'), 'name'),
        );
    }

    /**
     * A listener's join fetches the page with a Paginator, which keeps the
     * sort by the hidden select, whether the listener fetches the join or not
     */
    #[DataProvider('fetchJoinProvider')]
    public function testListenerJoin(bool $fetch): void
    {
        $driver = $this->getDriver();
        $driver->get(EventDispatcher::class)->subscribeTo(
            'artists.join',
            static function (QueryBuilderEvent $event) use ($fetch): void {
                $event->getQueryBuilder()->leftJoin('entity.recordings', 'recording');

                if (! $fetch) {
                    return;
                }

                $event->getQueryBuilder()->addSelect('recording');
            },
        );

        $result = $this->execute(
            $driver,
            '{ artists (filter: { recordingCount: { sort: DESC } }, first: 2) '
            . '{ totalCount edges { node { name recordingCount } } } }',
            'artists.join',
        );

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(5, $result['data']['artists']['totalCount']);
        $this->assertSame(
            [['name' => 'Phish', 'recordingCount' => 4], ['name' => "Gov't Mule", 'recordingCount' => 3]],
            array_column($result['data']['artists']['edges'], 'node'),
        );
    }

    /** @return array<string, array{bool}> */
    public static function fetchJoinProvider(): array
    {
        return [
            'joined' => [false],
            'fetch joined' => [true],
        ];
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function collectionProvider(): array
    {
        $filters = [
            'without arguments' => '{ recordingCount: { gt: 0, sort: DESC } }',
            // A sort binds the argument's parameter in its hidden select
            'with arguments' => '{ recordingCountSince: { args: { year: 1970 }, gt: 0, sort: DESC } }',
        ];

        $configs = [
            'batched' => [],
            // Each source's page is queried
            'over the batch limit' => ['batchLimit' => 1],
            'not batched' => ['batchAssociations' => false],
        ];

        $cases = [];
        foreach ($configs as $configName => $config) {
            foreach ($filters as $filterName => $filter) {
                $cases[$configName . ', ' . $filterName] = [$config, $filter];
            }
        }

        return $cases;
    }

    /**
     * A collection is filtered and sorted by a computed field however it is
     * fetched, and counted
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('collectionProvider')]
    public function testCollection(array $config, string $filter): void
    {
        $result = $this->execute(
            $this->getDriver($config),
            '{ labels { edges { node { name artists (filter: ' . $filter . ') '
            . '{ totalCount edges { node { name } } } } } } }',
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

    /**
     * A computed field with an expression keeps it and its excluded filters in
     * the metadata; one without has neither key
     */
    public function testMetadata(): void
    {
        $metadata = $this->getDriver()->get(Metadata::class)->toArray();
        $artist   = $metadata[ComputedExpressionArtist::class];
        assert(is_array($artist));

        $this->assertSame('UPPER({entity}.name)', $artist['computedFields']['upperName']['expression']);
        $this->assertSame(['in', 'notin'], $artist['computedFields']['upperName']['excludeFilters']);
        $this->assertSame(
            ['in', 'notin', 'isnull', 'contains', 'startswith', 'endswith'],
            $artist['computedFields']['recordingCount']['excludeFilters'],
        );
        $this->assertArrayNotHasKey('excludeFilters', $artist['computedFields']['nameLength']);
        $this->assertArrayNotHasKey('expression', $artist['computedFields']['displayName']);
        $this->assertArrayNotHasKey('excludeFilters', $artist['computedFields']['displayName']);
    }
}
