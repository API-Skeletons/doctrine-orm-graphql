<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as FilterQueryBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\Expr\OrderBy;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;

/**
 * Sorted fields are ordered by sortPriority, lowest first, then fields
 * without a priority by field name
 */
class SortPriorityTest extends TestCase
{
    /** @return array<string, array{array<string, array<string, mixed>>, string[]}> */
    public static function sortProvider(): array
    {
        return [
            'every field has a priority' => [
                [
                    'venue' => ['sort' => 'ASC', 'sortPriority' => 2],
                    'city' => ['sort' => 'DESC', 'sortPriority' => 1],
                ],
                ['entity.city DESC', 'entity.venue ASC'],
            ],
            'only one field has a priority' => [
                [
                    'venue' => ['sort' => 'ASC'],
                    'city' => ['sort' => 'DESC', 'sortPriority' => 1],
                ],
                ['entity.city DESC', 'entity.venue ASC'],
            ],
            'fields without a priority are ordered by name' => [
                [
                    'venue' => ['sort' => 'ASC'],
                    'state' => ['sort' => 'ASC'],
                    'city' => ['sort' => 'DESC', 'sortPriority' => 5],
                ],
                ['entity.city DESC', 'entity.state ASC', 'entity.venue ASC'],
            ],
            'equal priorities are ordered by name' => [
                [
                    'venue' => ['sort' => 'ASC', 'sortPriority' => 1],
                    'city' => ['sort' => 'DESC', 'sortPriority' => 1],
                ],
                ['entity.city DESC', 'entity.venue ASC'],
            ],
            'no field has a priority' => [
                [
                    'venue' => ['sort' => 'ASC'],
                    'city' => ['sort' => 'DESC'],
                ],
                ['entity.city DESC', 'entity.venue ASC'],
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $filters
     * @param string[]                            $expectedOrderBy
     */
    #[DataProvider('sortProvider')]
    public function testSortOrder(array $filters, array $expectedOrderBy): void
    {
        $driver = new Driver($this->getEntityManager());
        $entity = $driver->get(EntityTypeContainer::class)->get(Performance::class);

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from(Performance::class, 'entity');

        (new FilterQueryBuilder())->apply($filters, $queryBuilder, $entity);

        $this->assertSame(
            $expectedOrderBy,
            array_map(static fn (OrderBy $orderBy): string => (string) $orderBy, $queryBuilder->getDQLPart('orderBy')),
        );
    }

    /**
     * The error names the field as the client did, by its alias
     */
    public function testSortPriorityWithoutSortNamesTheAlias(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ExtractionMap']));
        $entity = $driver->get(EntityTypeContainer::class)->get(Performance::class);

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from(Performance::class, 'entity');

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage("Sort direction for field 'date' is not set but a sortPriority was.");

        (new FilterQueryBuilder())->apply(['date' => ['sortPriority' => 1]], $queryBuilder, $entity);
    }
}
