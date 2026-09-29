<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ToOneLoader;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestCompositeKeyEntity;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use DateTime;
use GraphQL\Deferred;
use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_map;

/**
 * Unloaded to-one associations are loaded with one query per class
 */
class ToOneBatchTest extends QueryCountingTestCase
{
    /** @return array{mixed[], int} The result and the number of queries */
    private function execute(bool $batch, string $query): array
    {
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['batchAssociations' => $batch]));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'performance' => $driver->completeConnection(Performance::class),
                    'artist' => $driver->completeConnection(Artist::class),
                ],
            ]),
        ]);

        $this->resetQueries();
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return [$result, $this->queryCount()];
    }

    public function testToOneAssociationsAreLoadedInOneQuery(): void
    {
        $query = '{ performance { edges { node { venue artist { name } } } } }';

        [$unbatched, $unbatchedQueries] = $this->execute(false, $query);
        [$batched, $batchedQueries]     = $this->execute(true, $query);

        $this->assertSame($unbatched, $batched);

        // A page of performances, then one lazy load per distinct artist, or
        // one query for all of them
        $this->assertSame(5, $unbatchedQueries);
        $this->assertSame(2, $batchedQueries);
    }

    /**
     * The artists are loaded by the root connection, so resolving each
     * performance's artist costs no query
     */
    public function testLoadedTargetsCostNoQueries(): void
    {
        $withArtist    = '{ artist { edges { node { name performances { edges { node { venue artist { name } } } } } } } }';
        $withoutArtist = '{ artist { edges { node { name performances { edges { node { venue } } } } } } }';

        [$unbatched]                   = $this->execute(false, $withArtist);
        [$batched, $withArtistQueries] = $this->execute(true, $withArtist);
        [, $withoutArtistQueries]      = $this->execute(true, $withoutArtist);

        $this->assertSame($unbatched, $batched);
        $this->assertSame($withoutArtistQueries, $withArtistQueries);
    }

    public function testIdentifiersAreLoadedInChunks(): void
    {
        $em = $this->getEntityManager();
        $em->clear();
        $loader = new ToOneLoader($em, 2);

        $ids = array_map(static fn (Artist $artist): int => $artist->getId(), $em->getRepository(Artist::class)->findAll());
        $em->clear();
        $proxies = array_map(static fn (int $id): Artist => $em->getReference(Artist::class, $id), $ids);

        $this->resetQueries();
        $deferreds = array_map(static fn (Artist $proxy): object => $loader->defer($proxy), $proxies);
        foreach ($deferreds as $deferred) {
            $this->assertInstanceOf(Deferred::class, $deferred);
        }

        SyncPromise::runQueue();

        // Four artists in chunks of two
        $this->assertSame(2, $this->queryCount());
        foreach ($proxies as $proxy) {
            $this->assertFalse($em->getUnitOfWork()->isUninitializedObject($proxy));
        }
    }

    public function testLoadedEntitiesAndOtherObjectsAreReturnedUnchanged(): void
    {
        $em     = $this->getEntityManager();
        $loader = new ToOneLoader($em);

        $artist = $em->getRepository(Artist::class)->findOneBy([]);
        $date   = new DateTime();

        $this->assertSame($artist, $loader->defer($artist));
        $this->assertSame($date, $loader->defer($date));
    }

    public function testCompositeIdentifierIsNotBatched(): void
    {
        $em = $this->getEntityManager();
        $em->persist(new TestCompositeKeyEntity(1, 2, 'composite'));
        $em->flush();
        $em->clear();

        $proxy = $em->getReference(TestCompositeKeyEntity::class, ['firstId' => 1, 'secondId' => 2]);

        $this->assertSame($proxy, (new ToOneLoader($em))->defer($proxy));
        $this->assertSame('composite', $proxy->getName());
    }
}
