<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryRecording;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

use function array_column;
use function count;

/**
 * Computed fields of RepositoryArtist, which need the entity manager
 *
 * @extends EntityRepository<RepositoryArtist>
 */
class RepositoryArtistRepository extends EntityRepository
{
    /** The number of times each batched method was called */
    public static int $batchCalls = 0;

    /** @param ClassMetadata<RepositoryArtist> $class */
    public function __construct(
        EntityManagerInterface $em,
        ClassMetadata $class,
        private readonly string $prefix = '',
    ) {
        parent::__construct($em, $class);
    }

    /** Uses the repository's own state, which a repository factory may give it */
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryComputed')]
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryCollision')]
    #[GraphQL\ComputedField(type: 'string', group: 'RepositoryFieldCollision', name: 'name')]
    public function getDisplayName(RepositoryArtist $artist): string
    {
        return $this->prefix . $artist->getName();
    }

    /** Not batched: a query for each artist */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryComputed')]
    public function getRecordingTotal(RepositoryArtist $artist, int|null $year = null): int
    {
        return (int) $this->recordings($year)
            ->select('COUNT(r.id)')
            ->andWhere('r.artist = :artist')
            ->setParameter('artist', $artist)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Batched, and filtered and sorted by its expression.  An artist without
     * recordings is left out, so its value is null.
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(
        type: 'int',
        group: 'RepositoryComputed',
        expression: '(SELECT COUNT({r}.id) FROM ' . RepositoryRecording::class . ' {r} WHERE {r}.artist = {entity}'
            . ' AND ({:year} IS NULL OR {r}.released BETWEEN '
            . "CONCAT({:year}, '-01-01') AND CONCAT({:year}, '-12-31')))",
        batch: true,
    )]
    public function getRecordingCount(Collection $artists, int|null $year = null): array
    {
        self::$batchCalls++;

        return array_column(
            $this->recordings($year)
                ->select('IDENTITY(r.artist) AS id', 'COUNT(r.id) AS total')
                ->andWhere('r.artist IN (:artists)')
                ->groupBy('r.artist')
                ->setParameter('artists', $artists->getKeys())
                ->getQuery()
                ->getScalarResult(),
            'total',
            'id',
        );
    }

    /**
     * An entity, returned in a Collection
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     *
     * @return Collection<int|string, RepositoryRecording>
     */
    #[GraphQL\ComputedField(type: RepositoryRecording::class, group: 'RepositoryComputed', batch: true)]
    public function getLatestRecording(Collection $artists): Collection
    {
        $latest = new ArrayCollection();

        foreach ($this->recordingsOf($artists) as $row) {
            $latest->set($row['artistId'], $row[0]);
        }

        return $latest;
    }

    /**
     * A list of entities
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     *
     * @return array<int|string, list<RepositoryRecording>>
     */
    #[GraphQL\ComputedField(type: RepositoryRecording::class, group: 'RepositoryComputed', list: true, batch: true)]
    public function getRecordingList(Collection $artists): array
    {
        $recordings = [];

        foreach ($this->recordingsOf($artists) as $row) {
            $recordings[$row['artistId']][] = $row[0];
        }

        return $recordings;
    }

    /**
     * A non-null type, which an artist without recordings has no value of
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'RepositoryNonNullInt', group: 'RepositoryNonNull', batch: true)]
    public function getRequiredCount(Collection $artists): array
    {
        return $this->getRecordingCount($artists);
    }

    /**
     * Not an array or a Collection
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     */
    #[GraphQL\ComputedField(type: 'int', group: 'RepositoryInvalidReturn', batch: true)]
    public function getBroken(Collection $artists): int
    {
        return count($artists);
    }

    /**
     * The recordings of artists, oldest first, with the identifier of each
     * recording's artist
     *
     * @param Collection<int|string, RepositoryArtist> $artists
     *
     * @return list<array{0: RepositoryRecording, artistId: int}>
     */
    private function recordingsOf(Collection $artists): array
    {
        /** @var list<array{0: RepositoryRecording, artistId: int}> $rows */
        $rows = $this->recordings(null)
            ->select('r', 'IDENTITY(r.artist) AS artistId')
            ->andWhere('r.artist IN (:artists)')
            ->orderBy('r.released')
            ->setParameter('artists', $artists->getKeys())
            ->getQuery()
            ->getResult();

        return $rows;
    }

    private function recordings(int|null $year): QueryBuilder
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->from(RepositoryRecording::class, 'r');

        if ($year !== null) {
            $queryBuilder
                ->andWhere('r.released BETWEEN :from AND :to')
                ->setParameter('from', new DateTimeImmutable($year . '-01-01'), 'date_immutable')
                ->setParameter('to', new DateTimeImmutable($year . '-12-31'), 'date_immutable');
        }

        return $queryBuilder;
    }
}
