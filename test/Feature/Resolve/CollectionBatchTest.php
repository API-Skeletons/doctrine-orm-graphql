<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestCompositeKeyEntity;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function base64_encode;
use function sprintf;

/**
 * Batched collections return exactly what per-parent queries return
 */
class CollectionBatchTest extends QueryCountingTestCase
{
    private const string CONNECTION = '{ totalCount pageInfo { hasNextPage hasPreviousPage startCursor endCursor } '
        . 'edges { cursor node { id } } }';

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $roots  The root fields and their entities
     *
     * @return array{mixed[], int} The result and the number of queries
     */
    private function execute(array $config, string $query, array|null $roots = null): array
    {
        $this->getEntityManager()->clear();

        $roots ??= [
            'artist' => Artist::class,
            'performance' => Performance::class,
            'recording' => Recording::class,
            'user' => User::class,
        ];

        $driver = new Driver($this->getEntityManager(), new Config($config));
        $fields = [];
        foreach ($roots as $name => $entityClass) {
            $fields[$name] = $driver->completeConnection($entityClass);
        }

        $schema = new Schema([
            'query' => new ObjectType(['name' => 'query', 'fields' => $fields]),
        ]);

        $this->resetQueries();
        $result = GraphQL::executeQuery($schema, $query)->toArray();

        return [$result, $this->queryCount()];
    }

    private static function cursor(int $index): string
    {
        return base64_encode((string) $index);
    }

    /**
     * Arguments for a collection, in every form of pagination
     *
     * @return array<string, string>
     */
    private static function arguments(string $sortField): array
    {
        return [
            'no arguments' => '',
            'first' => '(first: 2)',
            'first after' => sprintf('(first: 1, after: "%s")', self::cursor(0)),
            'last' => '(last: 2)',
            'last before' => sprintf('(last: 1, before: "%s")', self::cursor(2)),
            'after before' => sprintf('(after: "%s", before: "%s")', self::cursor(0), self::cursor(3)),
            'first zero' => '(first: 0)',
            'last beyond the end' => '(last: 1000)',
            'sort' => sprintf('(filter: { %s: { sort: DESC } })', $sortField),
            'sort and page' => sprintf('(filter: { %s: { sort: DESC } }, first: 1, after: "%s")', $sortField, self::cursor(0)),
        ];
    }

    /** @return array<string, array{string}> */
    public static function queryProvider(): array
    {
        $queries = [];

        $paths = [
            'one-to-many' => ['artist', 'performances', 'venue'],
            'one-to-many, mostly empty' => ['performance', 'recordings', 'source'],
            'many-to-many owning' => ['user', 'recordings', 'source'],
            'many-to-many inverse' => ['recording', 'users', 'name'],
        ];

        foreach ($paths as $pathName => [$root, $association, $sortField]) {
            foreach (self::arguments($sortField) as $argumentsName => $arguments) {
                $queries[$pathName . ', ' . $argumentsName] = [
                    sprintf('{ %s { edges { node { id %s%s %s } } } }', $root, $association, $arguments, self::CONNECTION),
                ];
            }
        }

        $queries['nested'] = [
            '{ artist { edges { node { id performances(first: 3) { totalCount edges { node { id '
            . 'recordings(last: 1) ' . self::CONNECTION . ' } } } } } } }',
        ];

        $queries['filter and page'] = [
            '{ artist { edges { node { id performances(filter: { venue: { isnull: false } }, first: 2) '
            . self::CONNECTION . ' } } } }',
        ];

        return $queries;
    }

    #[DataProvider('queryProvider')]
    public function testBatchedResultsMatchPerParentResults(string $query): void
    {
        [$perParent, $perParentQueries] = $this->execute(['batchAssociations' => false], $query);
        [$batched, $batchedQueries]     = $this->execute(['batchAssociations' => true], $query);

        $this->assertArrayNotHasKey('errors', $perParent);
        $this->assertSame($perParent, $batched);
        $this->assertLessThanOrEqual($perParentQueries, $batchedQueries);
    }

    /**
     * An invalid argument is an error of every source's field, batched or not
     */
    public function testErrorsMatchPerParentErrors(): void
    {
        $query = '{ artist { edges { node { id performances(first: 1, after: "LTU=") ' . self::CONNECTION . ' } } } }';

        [$perParent] = $this->execute(['batchAssociations' => false], $query);
        [$batched]   = $this->execute(['batchAssociations' => true], $query);

        $this->assertCount(4, $perParent['errors']);
        $this->assertSame($perParent, $batched);
    }

    /**
     * A collection of entities with a composite identifier is not batched; its
     * rows are still each source's own
     */
    public function testCompositeIdentifierTargetIsResolvedPerSource(): void
    {
        $em      = $this->getEntityManager();
        $artists = $em->getRepository(Artist::class)->findBy([], ['id' => 'ASC']);
        $em->persist(new TestCompositeKeyEntity(1, 1, 'first', $artists[0]));
        $em->persist(new TestCompositeKeyEntity(1, 2, 'second', $artists[0]));
        $em->persist(new TestCompositeKeyEntity(2, 1, 'third', $artists[1]));
        $em->flush();

        $query = '{ artist { edges { node { id compositeKeyEntities { totalCount edges { node { name } } } } } } }';

        $roots = ['artist' => Artist::class];

        [$perParent] = $this->execute(['batchAssociations' => false, 'group' => 'CompositeKeyTest'], $query, $roots);
        [$batched]   = $this->execute(['batchAssociations' => true, 'group' => 'CompositeKeyTest'], $query, $roots);

        $this->assertArrayNotHasKey('errors', $batched);
        $this->assertSame($perParent, $batched);

        $totals = [];
        foreach ($batched['data']['artist']['edges'] as $edge) {
            $totals[$edge['node']['id']] = $edge['node']['compositeKeyEntities']['totalCount'];
        }

        $this->assertSame(2, $totals[$artists[0]->getId()]);
        $this->assertSame(1, $totals[$artists[1]->getId()]);
    }
}
