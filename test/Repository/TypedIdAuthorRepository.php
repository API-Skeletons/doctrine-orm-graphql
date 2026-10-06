<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypedIdAuthor;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypedIdBook;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityRepository;

use function array_column;

/**
 * A batched computed field of an entity whose identifier is stored in
 * another form than PHP holds it
 *
 * @extends EntityRepository<TypedIdAuthor>
 */
class TypedIdAuthorRepository extends EntityRepository
{
    /** @var list<int|string> The keys of the entities the method was last given */
    public static array $keys = [];

    /**
     * Keyed by IDENTITY(), the database value of the identifier, as the
     * entities are.  The keys are bound rather than the entities, which
     * Doctrine does not convert in an IN list.
     *
     * @param Collection<int|string, TypedIdAuthor> $authors
     *
     * @return array<int|string, int>
     */
    #[GraphQL\ComputedField(type: 'int', group: 'TypedIdRepository', batch: true)]
    public function getBookCount(Collection $authors): array
    {
        self::$keys = $authors->getKeys();

        return array_column(
            $this->getEntityManager()->createQueryBuilder()
                ->select('IDENTITY(b.author) AS id', 'COUNT(b.id) AS total')
                ->from(TypedIdBook::class, 'b')
                ->where('b.author IN (:authors)')
                ->groupBy('b.author')
                ->setParameter('authors', $authors->getKeys())
                ->getQuery()
                ->getScalarResult(),
            'total',
            'id',
        );
    }
}
