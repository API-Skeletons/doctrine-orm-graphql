<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\ComputedFieldMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ComputedFieldBatchLoader;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryLabel;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryRecording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryArtistRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;
use GraphQL\Deferred;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function array_column;
use function array_keys;
use function array_map;
use function assert;
use function is_array;
use function strlen;

/**
 * Computed fields on methods of an entity's repository, batched or not
 */
class RepositoryComputedFieldTest extends QueryCountingTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->populateRepositoryData($this->getEntityManager());
        RepositoryArtistRepository::$batchCalls = 0;
        $this->resetQueries();
    }

    /**
     * Two labels and five artists, by identifier: Phish (Elektra, 4
     * recordings), Ween (Elektra, 2), moe. (Elektra, 0), Gov't Mule (Relix, 3)
     * and Grateful Dead (Relix, 1)
     */
    private function populateRepositoryData(EntityManager $entityManager): void
    {
        $labels = [];
        foreach (['Elektra', 'Relix'] as $name) {
            $labels[$name] = new RepositoryLabel($name);
            $entityManager->persist($labels[$name]);
        }

        $artists = [
            ['Phish', 'Elektra', ['2002-05-14', '2002-11-05', '2003-10-14', '2004-06-15']],
            ['Ween', 'Elektra', ['2003-07-15', '2007-10-23']],
            ['moe.', 'Elektra', []],
            ["Gov't Mule", 'Relix', ['2001-06-12', '2003-09-23', '2004-08-17']],
            ['Grateful Dead', 'Relix', ['1977-05-08']],
        ];

        foreach ($artists as [$name, $label, $releases]) {
            $artist = (new RepositoryArtist($name))->setLabel($labels[$label]);
            $entityManager->persist($artist);

            foreach ($releases as $i => $released) {
                $entityManager->persist(new RepositoryRecording(
                    $name . ' ' . $i,
                    new DateTimeImmutable($released),
                    $artist,
                ));
            }
        }

        $entityManager->flush();
        $entityManager->clear();
    }

    /** @param array<string, mixed> $config */
    private function getDriver(array $config = [], EntityManager|null $entityManager = null): Driver
    {
        return new Driver(
            $entityManager ?? $this->getEntityManager(),
            new Config(['group' => 'RepositoryComputed'] + $config),
        );
    }

    /** @return array<array-key, mixed> */
    private function execute(Driver $driver, string $query): array
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(RepositoryArtist::class),
                    'labels' => $driver->completeConnection(RepositoryLabel::class),
                ],
            ]),
        ]);

        $this->getEntityManager()->clear();
        $this->resetQueries();

        return GraphQL::executeQuery($schema, $query)->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);
    }

    /**
     * The nodes of the artists connection
     *
     * @return list<array<string, mixed>>
     */
    private function artists(Driver $driver, string $fields, string $args = ''): array
    {
        $result = $this->execute($driver, '{ artists' . $args . ' { edges { node { ' . $fields . ' } } } }');

        $this->assertArrayNotHasKey('errors', $result);
        assert(is_array($result['data']) && is_array($result['data']['artists']));

        /** @var list<array{node: array<string, mixed>}> $edges */
        $edges = $result['data']['artists']['edges'];

        return array_column($edges, 'node');
    }

    public function testUnbatchedFields(): void
    {
        $artists = $this->artists(
            $this->getDriver(),
            'name displayName recordingTotal in2003: recordingTotal(year: 2003)',
        );

        $this->assertSame(
            [
                ['name' => 'Phish', 'displayName' => 'Phish', 'recordingTotal' => 4, 'in2003' => 1],
                ['name' => 'Ween', 'displayName' => 'Ween', 'recordingTotal' => 2, 'in2003' => 1],
                ['name' => 'moe.', 'displayName' => 'moe.', 'recordingTotal' => 0, 'in2003' => 0],
                ['name' => "Gov't Mule", 'displayName' => "Gov't Mule", 'recordingTotal' => 3, 'in2003' => 1],
                ['name' => 'Grateful Dead', 'displayName' => 'Grateful Dead', 'recordingTotal' => 1, 'in2003' => 0],
            ],
            $artists,
        );
    }

    public function testBatchedFieldIsOneQuery(): void
    {
        $artists = $this->artists($this->getDriver(), 'name recordingCount');

        // An artist the method gives no value for is null
        $this->assertSame([4, 2, null, 3, 1], array_column($artists, 'recordingCount'));
        $this->assertSame(1, RepositoryArtistRepository::$batchCalls);

        // The artists and their counts
        $this->assertSame(2, $this->queryCount());
    }

    public function testBatchedFieldInABatchedCollection(): void
    {
        $result = $this->execute(
            $this->getDriver(),
            '{ labels { edges { node { name artists { edges { node { name recordingCount } } } } } } }',
        );

        $this->assertArrayNotHasKey('errors', $result);
        assert(is_array($result['data']) && is_array($result['data']['labels']));

        $counts = [];
        /** @var list<array{node: array{name: string, artists: array{edges: list<array{node: array{name: string, recordingCount: int|null}}>}}}> $labels */
        $labels = $result['data']['labels']['edges'];
        foreach ($labels as $label) {
            foreach ($label['node']['artists']['edges'] as $artist) {
                $counts[$label['node']['name']][$artist['node']['name']] = $artist['node']['recordingCount'];
            }
        }

        $this->assertSame(
            [
                'Elektra' => ['Phish' => 4, 'Ween' => 2, 'moe.' => null],
                'Relix' => ["Gov't Mule" => 3, 'Grateful Dead' => 1],
            ],
            $counts,
        );

        // The artists of both labels in one call
        $this->assertSame(1, RepositoryArtistRepository::$batchCalls);
    }

    public function testArgumentsUnderAliasesAreBatchedSeparately(): void
    {
        $artists = $this->artists(
            $this->getDriver(),
            'all: recordingCount in2003: recordingCount(year: 2003) again: recordingCount',
        );

        $this->assertSame([4, 2, null, 3, 1], array_column($artists, 'all'));
        $this->assertSame([1, 1, null, 1, null], array_column($artists, 'in2003'));
        $this->assertSame([4, 2, null, 3, 1], array_column($artists, 'again'));
        $this->assertSame(2, RepositoryArtistRepository::$batchCalls);
    }

    public function testFilteredAndSortedByItsExpression(): void
    {
        $artists = $this->artists(
            $this->getDriver(),
            'name recordingCount',
            '(filter: { recordingCount: { gt: 1, sort: DESC } })',
        );

        $this->assertSame(
            [
                ['name' => 'Phish', 'recordingCount' => 4],
                ['name' => "Gov't Mule", 'recordingCount' => 3],
                ['name' => 'Ween', 'recordingCount' => 2],
            ],
            $artists,
        );

        $artists = $this->artists(
            $this->getDriver(),
            'name',
            '(filter: { recordingCount: { args: { year: 2003 }, eq: 1 } })',
        );

        $this->assertSame(['Phish', 'Ween', "Gov't Mule"], array_column($artists, 'name'));
    }

    public function testEntityTypedFields(): void
    {
        $artists = $this->artists(
            $this->getDriver(),
            'name latestRecording { title } recordingList { title }',
        );

        $this->assertSame(
            [
                'Phish 3',
                'Ween 1',
                null,
                "Gov't Mule 2",
                'Grateful Dead 0',
            ],
            array_map(
                static fn (array $artist): mixed => is_array($artist['latestRecording'])
                    ? $artist['latestRecording']['title']
                    : $artist['latestRecording'],
                $artists,
            ),
        );

        $this->assertSame(
            [['title' => 'Ween 0'], ['title' => 'Ween 1']],
            $artists[1]['recordingList'],
        );
        $this->assertNull($artists[2]['recordingList']);

        // The artists, and the recordings of each field, which loads them
        $this->assertSame(3, $this->queryCount());
    }

    public function testNotBatchedWhenBatchingIsOff(): void
    {
        $artists = $this->artists(
            $this->getDriver(['batchAssociations' => false]),
            'recordingCount in2003: recordingCount(year: 2003) latestRecording { title }',
        );

        $this->assertSame([4, 2, null, 3, 1], array_column($artists, 'recordingCount'));
        $this->assertSame([1, 1, null, 1, null], array_column($artists, 'in2003'));
        $this->assertSame('Phish 3', $artists[0]['latestRecording']['title'] ?? null);

        // The method is given each artist alone
        $this->assertSame(10, RepositoryArtistRepository::$batchCalls);
    }

    public function testExtractLeavesOutBatchedFields(): void
    {
        $driver   = $this->getDriver();
        $hydrator = $driver->get(HydratorContainer::class)->get(RepositoryArtist::class);
        assert($hydrator instanceof DoctrineObjectWithComputed);

        $artist = $this->getEntityManager()->getRepository(RepositoryArtist::class)->findOneBy(['name' => 'Phish']);
        assert($artist instanceof RepositoryArtist);

        $values = $hydrator->extract($artist);

        // A field with arguments and a batched field are left out
        $this->assertSame('Phish', $values['displayName']);
        $this->assertArrayNotHasKey('recordingTotal', $values);
        $this->assertArrayNotHasKey('recordingCount', $values);
        $this->assertArrayNotHasKey('latestRecording', $values);

        // A batched field is computed alone when asked for
        $this->assertSame(1, $hydrator->extractComputedField($artist, 'recordingCount', ['year' => 2004]));
        $this->assertNotNull($hydrator->getBatchExtractor('recordingCount'));
        $this->assertNull($hydrator->getBatchExtractor('displayName'));
    }

    public function testAMissingValueOfANonNullTypeIsAnError(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'RepositoryNonNull']));
        $driver->get(TypeContainer::class)->set('RepositoryNonNullInt', static fn () => Type::nonNull(Type::int()));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artists' => $driver->completeConnection(RepositoryArtist::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ artists { edges { node { id requiredCount } } } }')
            ->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->assertCount(1, $result['errors'] ?? []);
        $this->assertSame('Internal server error', $result['errors'][0]['message']);
        $this->assertStringContainsString('Cannot return null for non-nullable field', $result['errors'][0]['extensions']['debugMessage']);
        $this->assertSame(['artists', 'edges', 2, 'node', 'requiredCount'], $result['errors'][0]['path']);
    }

    public function testAMethodWhichReturnsNeitherAnArrayNorACollectionIsAnErrorOfEveryEntity(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'RepositoryInvalidReturn']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['artists' => $driver->completeConnection(RepositoryArtist::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ artists { edges { node { id broken } } } }')
            ->toArray(DebugFlag::INCLUDE_DEBUG_MESSAGE);

        $this->assertCount(5, $result['errors'] ?? []);
        foreach ($result['errors'] as $error) {
            $this->assertSame('Internal server error', $error['message']);
            $this->assertSame(
                'Computed field broken of entity ' . RepositoryArtist::class . ' is batched, but its method did not '
                . 'return an array or a Collection of values keyed by identifier.',
                $error['extensions']['debugMessage'],
            );
        }
    }

    public function testTheEntityManagersRepositoryFactoryGivesTheRepository(): void
    {
        $entityManager = $this->getEntityManager();
        $config        = clone $entityManager->getConfiguration();
        $config->setRepositoryFactory(new class implements RepositoryFactory {
            /** @var array<string, EntityRepository<object>> */
            private array $repositories = [];

            /**
             * @param class-string<T> $entityName
             *
             * @return EntityRepository<T>
             *
             * @template T of object
             */
            public function getRepository(EntityManagerInterface $entityManager, $entityName): EntityRepository // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
            {
                /** @var EntityRepository<T> $repository */
                $repository = $this->repositories[$entityName] ??= $entityName === RepositoryArtist::class
                    ? new RepositoryArtistRepository(
                        $entityManager,
                        $entityManager->getClassMetadata(RepositoryArtist::class),
                        'The ',
                    )
                    : new EntityRepository($entityManager, $entityManager->getClassMetadata($entityName));

                return $repository;
            }
        });

        $driver = $this->getDriver([], new EntityManager($entityManager->getConnection(), $config));

        $this->assertSame(
            ['The Phish', 'The Ween', 'The moe.', "The Gov't Mule", 'The Grateful Dead'],
            array_column($this->artists($driver, 'displayName'), 'displayName'),
        );
    }

    public function testMethodIsCalledForEachChunk(): void
    {
        $entityManager = $this->getEntityManager();
        $artists       = $entityManager->getRepository(RepositoryArtist::class)->findBy([], ['id' => 'ASC']);
        $loader        = new ComputedFieldBatchLoader($entityManager, 2);

        $chunks    = [];
        $extractor = static function (Collection $artists, array $args) use (&$chunks): array {
            $chunks[] = $artists->getKeys();

            return $artists->map(static fn (RepositoryArtist $artist): int => strlen($artist->getName()))->toArray();
        };

        $deferred = array_map(
            static fn (RepositoryArtist $artist): Deferred => $loader->defer(
                $artist,
                RepositoryArtist::class,
                'nameLength',
                $extractor,
                [],
            ),
            $artists,
        );
        SyncPromise::runQueue();

        $this->assertSame([[1, 2], [3, 4], [5]], $chunks);
        $this->assertSame([5, 4, 4, 10, 13], array_map(static fn (Deferred $value): mixed => $value->result, $deferred));
    }

    public function testMetadata(): void
    {
        $driver   = $this->getDriver();
        $metadata = $driver->get(Metadata::class)->toArray();

        $computedFields = $metadata[RepositoryArtist::class]['computedFields'];
        $this->assertTrue($computedFields['displayName']['repository']);
        $this->assertArrayNotHasKey('batch', $computedFields['displayName']);
        $this->assertTrue($computedFields['recordingCount']['repository']);
        $this->assertTrue($computedFields['recordingCount']['batch']);

        // The first parameter of the method is not an argument
        $this->assertSame([], $computedFields['displayName']['args']);
        $this->assertSame(['year'], array_keys($computedFields['recordingCount']['args']));

        // Cached metadata gives the same fields
        $cached  = new Driver($this->getEntityManager(), new Config(['group' => 'RepositoryComputed']), $metadata);
        $artists = $this->artists($cached, 'displayName recordingCount');
        $this->assertSame(['Phish', 4], [$artists[0]['displayName'], $artists[0]['recordingCount']]);
    }

    public function testBatchedMetadataMustBeOfARepository(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Metadata for field count is batched but not of a repository.  Only a method of a repository is batched.',
        );

        ComputedFieldMetadata::fromArray(
            [
                'method' => 'getCount',
                'type' => 'int',
                'name' => 'count',
                'description' => null,
                'list' => false,
                'args' => [],
                'batch' => true,
            ],
            'field count',
        );
    }
}
