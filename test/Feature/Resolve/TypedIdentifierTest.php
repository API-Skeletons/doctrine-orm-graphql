<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\DbalType\Code;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypedIdAuthor;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypedIdBook;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypedIdTag;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\TypedIdAuthorRepository;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_map;

/**
 * Identifiers stored in another form than PHP holds them, as binary UUIDs
 * are, are matched as their database values: in batched collections, in
 * batched to-one associations and in filters
 */
class TypedIdentifierTest extends QueryCountingTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $ann = new TypedIdAuthor(new Code('a'), 'Ann');
        $bob = new TypedIdAuthor(new Code('b'), 'Bob');
        $entityManager->persist($ann);
        $entityManager->persist($bob);
        $entityManager->persist(new TypedIdAuthor(new Code('c'), 'Cy'));

        $books = [
            'b1' => new TypedIdBook(new Code('b1'), 'One', $ann),
            'b2' => new TypedIdBook(new Code('b2'), 'Two', $ann),
            'b3' => new TypedIdBook(new Code('b3'), 'Three', $bob),
        ];

        $tags = [
            't1' => new TypedIdTag(new Code('t1')),
            't2' => new TypedIdTag(new Code('t2')),
            't3' => new TypedIdTag(new Code('t3')),
        ];

        $books['b1']->addTag($tags['t1']);
        $books['b1']->addTag($tags['t2']);
        $books['b3']->addTag($tags['t1']);

        foreach ([...$books, ...$tags] as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();
    }

    /** @return array{mixed[], int} The result and the number of queries */
    private function execute(string $query, bool $batch = true): array
    {
        $this->getEntityManager()->clear();

        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'TypedId', 'batchAssociations' => $batch]),
        );
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'authors' => $driver->completeConnection(TypedIdAuthor::class),
                    'books' => $driver->completeConnection(TypedIdBook::class),
                    'tags' => $driver->completeConnection(TypedIdTag::class),
                ],
            ]),
        ]);

        $this->resetQueries();
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return [$result, $this->queryCount()];
    }

    /**
     * The identifiers of a connection's nodes
     *
     * @param mixed[] $connection
     *
     * @return string[]
     */
    private static function ids(array $connection): array
    {
        return array_column(array_column($connection['edges'], 'node'), 'id');
    }

    /**
     * Each source's collection, batched and per source
     *
     * @return array<string, array{string, string, string, array<string, string[]>}>
     */
    public static function collectionProvider(): array
    {
        return [
            'one-to-many' => [
                '{ authors { edges { node { id books { totalCount edges { node { id } } } } } } }',
                'authors',
                'books',
                ['a' => ['b1', 'b2'], 'b' => ['b3'], 'c' => []],
            ],
            'one-to-many, paged' => [
                '{ authors { edges { node { id books (first: 1) { edges { node { id } } } } } } }',
                'authors',
                'books',
                ['a' => ['b1'], 'b' => ['b3'], 'c' => []],
            ],
            'one-to-many, filtered' => [
                '{ authors { edges { node { id books (filter: { id: { neq: "b1" } }) { edges { node { id } } } } } } }',
                'authors',
                'books',
                ['a' => ['b2'], 'b' => ['b3'], 'c' => []],
            ],
            'many-to-many, owning side' => [
                '{ books { edges { node { id tags { edges { node { id } } } } } } }',
                'books',
                'tags',
                ['b1' => ['t1', 't2'], 'b2' => [], 'b3' => ['t1']],
            ],
            'many-to-many, inverse side' => [
                '{ tags { edges { node { id books { edges { node { id } } } } } } }',
                'tags',
                'books',
                ['t1' => ['b1', 'b3'], 't2' => ['b1'], 't3' => []],
            ],
        ];
    }

    /** @param array<string, string[]> $expected The identifiers of each source's collection */
    #[DataProvider('collectionProvider')]
    public function testCollectionsAreBatched(string $query, string $field, string $collection, array $expected): void
    {
        [$unbatched, $unbatchedQueries] = $this->execute($query, false);
        [$batched, $batchedQueries]     = $this->execute($query);

        $this->assertSame($unbatched, $batched);
        $this->assertLessThan($unbatchedQueries, $batchedQueries);

        $collections = [];
        foreach ($batched['data'][$field]['edges'] as $edge) {
            $collections[$edge['node']['id']] = self::ids($edge['node'][$collection]);
        }

        $this->assertSame($expected, $collections);
    }

    public function testCountIsOfEachSource(): void
    {
        [$result] = $this->execute('{ authors { edges { node { id books { totalCount } } } } }');

        $this->assertSame(
            [2, 1, 0],
            array_map(
                static fn (array $edge): int => $edge['node']['books']['totalCount'],
                $result['data']['authors']['edges'],
            ),
        );
    }

    /**
     * A page of books, then the two authors with one query rather than one
     * each
     */
    public function testToOneAssociationsAreBatched(): void
    {
        $query = '{ books { edges { node { id author { name } } } } }';

        [$unbatched, $unbatchedQueries] = $this->execute($query, false);
        [$batched, $batchedQueries]     = $this->execute($query);

        $this->assertSame($unbatched, $batched);
        $this->assertSame(
            ['Ann', 'Ann', 'Bob'],
            array_map(
                static fn (array $edge): string => $edge['node']['author']['name'],
                $batched['data']['books']['edges'],
            ),
        );
        $this->assertSame(3, $unbatchedQueries);
        $this->assertSame(2, $batchedQueries);
    }

    /** @return array<string, array{string, string[]}> */
    public static function filterProvider(): array
    {
        return [
            'association eq' => ['{ author: { eq: "a" } }', ['b1', 'b2']],
            'association neq' => ['{ author: { neq: "a" } }', ['b3']],
            'association in' => ['{ author: { in: ["b", "c"] } }', ['b3']],
            'association notin' => ['{ author: { notin: ["b"] } }', ['b1', 'b2']],
            'field eq' => ['{ id: { eq: "b3" } }', ['b3']],
            'field in' => ['{ id: { in: ["b1", "b3"] } }', ['b1', 'b3']],
        ];
    }

    /** @param string[] $expected */
    #[DataProvider('filterProvider')]
    public function testFilterValueIsConverted(string $filter, array $expected): void
    {
        [$result] = $this->execute('{ books (filter: ' . $filter . ') { edges { node { id } } } }');

        $this->assertSame($expected, self::ids($result['data']['books']));
    }

    /**
     * A value the type cannot convert is the client's error
     */
    public function testValueTheTypeCannotConvertIsAClientError(): void
    {
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'TypedId']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['books' => $driver->completeConnection(TypedIdBook::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ books (filter: { author: { eq: "x:y" } }) { edges { node { id } } } }')
            ->toArray();

        $this->assertSame(
            "Filter 'eq' of field 'author' is given a value which is not valid.",
            $result['errors'][0]['message'],
        );
    }

    public function testBatchedComputedFieldIsKeyedByTheDatabaseValue(): void
    {
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'TypedIdRepository']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['authors' => $driver->completeConnection(TypedIdAuthor::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{ authors { edges { node { name bookCount } } } }')->toArray();

        $this->assertSame(['code:a', 'code:b', 'code:c'], TypedIdAuthorRepository::$keys);
        $this->assertSame(
            [
                ['name' => 'Ann', 'bookCount' => 2],
                ['name' => 'Bob', 'bookCount' => 1],
                ['name' => 'Cy', 'bookCount' => null],
            ],
            array_column($result['data']['authors']['edges'], 'node'),
        );
    }
}
