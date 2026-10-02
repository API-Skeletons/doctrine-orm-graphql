<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedAuthor;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedBook;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\QueryCountingTestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function assert;

/**
 * A computed field may be of an entity type, or a list of one, which may be
 * of its own entity, and the entities it returns are loaded in a batch
 */
class ComputedEntityFieldTest extends QueryCountingTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $tolkien = new ComputedAuthor('Tolkien');
        $austen  = new ComputedAuthor('Austen');
        $entityManager->persist($tolkien);
        $entityManager->persist($austen);
        $entityManager->persist(new ComputedAuthor('Unpublished'));
        $entityManager->persist(new ComputedBook('The Hobbit', $tolkien));
        $entityManager->persist(new ComputedBook('The Silmarillion', $tolkien));
        $entityManager->persist(new ComputedBook('Emma', $austen));
        $entityManager->flush();
    }

    private function getSchema(Driver $driver): Schema
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'authors' => $driver->completeConnection(ComputedAuthor::class),
                    'books' => $driver->completeConnection(ComputedBook::class),
                ],
            ]),
        ]);
    }

    /** @return array{mixed[], int} The result and the number of queries */
    private function execute(string $query, bool $batch = true): array
    {
        $this->getEntityManager()->clear();

        $driver = new Driver(
            $this->getEntityManager(),
            new Config(['group' => 'ComputedEntity', 'batchAssociations' => $batch]),
        );
        $schema = $this->getSchema($driver);

        $this->resetQueries();
        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return [$result, $this->queryCount()];
    }

    public function testSchemaIsValid(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntity']));

        $this->getSchema($driver)->assertValid();
        $this->expectNotToPerformAssertions();
    }

    public function testFieldTypes(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntity']));
        $book   = $driver->type(ComputedBook::class);
        $author = $driver->type(ComputedAuthor::class);
        assert($book instanceof ObjectType && $author instanceof ObjectType);

        $this->assertSame($author, $book->getField('writer')->getType());
        $this->assertSame($author, $author->getField('self')->getType());

        $bookList = $author->getField('bookList')->getType();
        $this->assertInstanceOf(ListOfType::class, $bookList);
        $this->assertSame($book, $bookList->getWrappedType());

        $bookTitles = $author->getField('bookTitles')->getType();
        $this->assertInstanceOf(ListOfType::class, $bookTitles);
        $this->assertSame(Type::string(), $bookTitles->getWrappedType());
    }

    public function testDescriptionIsTheFieldsElseTheEntitys(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntity']));
        $book   = $driver->type(ComputedBook::class);
        $author = $driver->type(ComputedAuthor::class);
        assert($book instanceof ObjectType && $author instanceof ObjectType);

        $this->assertSame('The author itself', $author->getField('self')->description);
        $this->assertSame('An author', $book->getField('writer')->description);
    }

    public function testEntityIsResolved(): void
    {
        [$result] = $this->execute('{ books { edges { node { title writer { name } editor { name } } } } }');

        $this->assertSame([
            ['node' => ['title' => 'The Hobbit', 'writer' => ['name' => 'Tolkien'], 'editor' => null]],
            ['node' => ['title' => 'The Silmarillion', 'writer' => ['name' => 'Tolkien'], 'editor' => null]],
            ['node' => ['title' => 'Emma', 'writer' => ['name' => 'Austen'], 'editor' => null]],
        ], $result['data']['books']['edges']);
    }

    /**
     * A field of its own entity's type, nested
     */
    public function testEntityOfItsOwnType(): void
    {
        [$result] = $this->execute('{ authors (first: 1) { edges { node { name self { name self { name } } } } } }');

        $this->assertSame(
            ['name' => 'Tolkien', 'self' => ['name' => 'Tolkien', 'self' => ['name' => 'Tolkien']]],
            $result['data']['authors']['edges'][0]['node'],
        );
    }

    /**
     * An author's books, each with its author: a cycle of computed fields
     */
    public function testListOfEntities(): void
    {
        [$result] = $this->execute(
            '{ authors { edges { node { name bookList { title writer { name } } bookTitles } } } }',
        );

        $this->assertSame([
            [
                'node' => [
                    'name' => 'Tolkien',
                    'bookList' => [
                        ['title' => 'The Hobbit', 'writer' => ['name' => 'Tolkien']],
                        ['title' => 'The Silmarillion', 'writer' => ['name' => 'Tolkien']],
                    ],
                    'bookTitles' => ['The Hobbit', 'The Silmarillion'],
                ],
            ],
            [
                'node' => [
                    'name' => 'Austen',
                    'bookList' => [['title' => 'Emma', 'writer' => ['name' => 'Austen']]],
                    'bookTitles' => ['Emma'],
                ],
            ],
            ['node' => ['name' => 'Unpublished', 'bookList' => [], 'bookTitles' => []]],
        ], $result['data']['authors']['edges']);
    }

    /**
     * The entities a computed field returns, alone or in a list, are loaded
     * with one query, as an unloaded to-one association's are
     */
    public function testEntitiesAreLoadedInABatch(): void
    {
        foreach (['writer { name }', 'writers { name }'] as $field) {
            $query = '{ books { edges { node { title ' . $field . ' } } } }';

            [$unbatched, $unbatchedQueries] = $this->execute($query, false);
            [$batched, $batchedQueries]     = $this->execute($query);

            $this->assertSame($unbatched, $batched, $field);

            // A page of books, then a query for each of the two authors, or
            // one query for both
            $this->assertSame(3, $unbatchedQueries, $field);
            $this->assertSame(2, $batchedQueries, $field);
        }
    }

    public function testMetadataRecordsTheList(): void
    {
        $driver   = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntity']));
        $metadata = $driver->get(Metadata::class)->toArray();

        $this->assertTrue($metadata[ComputedAuthor::class]['computedFields']['bookList']['list']);
        $this->assertSame(ComputedBook::class, $metadata[ComputedAuthor::class]['computedFields']['bookList']['type']);
        $this->assertFalse($metadata[ComputedBook::class]['computedFields']['writer']['list']);

        // Cached metadata builds the same types
        $cached = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntity']), $metadata);
        $this->getSchema($cached)->assertValid();
    }

    public function testEntityNotExposedInTheGroupIsAnError(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedEntityUnexposed']));

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Computed field writer of entity ' . ComputedBook::class . ' is of type ' . ComputedAuthor::class
            . ', an entity which is not exposed in group ComputedEntityUnexposed.',
        );

        $driver->get(Metadata::class);
    }
}
