<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\InheritanceAnimal;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\InheritanceDog;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\InheritanceToy;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A row of an entity inheritance hierarchy may be of a subclass which is not
 * exposed itself.  It is resolved as its nearest exposed parent.
 */
class InheritanceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        $animal = new InheritanceAnimal('Generic');
        $dog    = new InheritanceDog('Rex', 'Labrador');

        $entityManager->persist($animal);
        $entityManager->persist($dog);
        $entityManager->persist(new InheritanceToy('Ball', $animal));
        $entityManager->persist(new InheritanceToy('Bone', $dog));
        $entityManager->persist(new InheritanceToy('Rope', $dog));
        $entityManager->flush();
        $entityManager->clear();
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $connections The entity class of each connection, by field
     *
     * @return mixed[]
     */
    private function execute(array $config, array $connections, string $query): array
    {
        $driver = new Driver($this->getEntityManager(), new Config($config));

        $fields = [];
        foreach ($connections as $fieldName => $entityClass) {
            $fields[$fieldName] = $driver->completeConnection($entityClass);
        }

        $schema = new Schema(['query' => new ObjectType(['name' => 'query', 'fields' => $fields])]);
        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data'];
    }

    /** @return array<string, array{bool, bool}> */
    public static function extractionProvider(): array
    {
        return [
            'batched, by value' => [true, true],
            'batched, by reference' => [true, false],
            'not batched, by value' => [false, true],
            'not batched, by reference' => [false, false],
        ];
    }

    #[DataProvider('extractionProvider')]
    public function testSubclassIsResolvedAsItsExposedParent(bool $batchAssociations, bool $extractByValue): void
    {
        $data = $this->execute(
            ['group' => 'Inheritance', 'batchAssociations' => $batchAssociations, 'extractByValue' => $extractByValue],
            ['animals' => InheritanceAnimal::class, 'toys' => InheritanceToy::class],
            '{
                animals { edges { node { name toys { totalCount edges { node { name } } } } } }
                toys { edges { node { name owner { name } } } }
            }',
        );

        $this->assertSame([
            ['node' => ['name' => 'Generic', 'toys' => ['totalCount' => 1, 'edges' => [['node' => ['name' => 'Ball']]]]]],
            [
                'node' => [
                    'name' => 'Rex',
                    'toys' => [
                        'totalCount' => 2,
                        'edges' => [
                            ['node' => ['name' => 'Bone']],
                            ['node' => ['name' => 'Rope']],
                        ],
                    ],
                ],
            ],
        ], $data['animals']['edges']);

        $this->assertSame([
            ['node' => ['name' => 'Ball', 'owner' => ['name' => 'Generic']]],
            ['node' => ['name' => 'Bone', 'owner' => ['name' => 'Rex']]],
            ['node' => ['name' => 'Rope', 'owner' => ['name' => 'Rex']]],
        ], $data['toys']['edges']);
    }

    public function testExposedSubclassIsResolvedAsItself(): void
    {
        $data = $this->execute(
            ['group' => 'InheritanceSubclass'],
            ['animals' => InheritanceAnimal::class, 'dogs' => InheritanceDog::class],
            '{
                animals { edges { node { name toys { totalCount } } } }
                dogs { edges { node { name breed toys { edges { node { name } } } } } }
            }',
        );

        $this->assertSame([
            ['node' => ['name' => 'Generic', 'toys' => ['totalCount' => 1]]],
            ['node' => ['name' => 'Rex', 'toys' => ['totalCount' => 2]]],
        ], $data['animals']['edges']);

        $this->assertSame([
            [
                'node' => [
                    'name' => 'Rex',
                    'breed' => 'Labrador',
                    'toys' => [
                        'edges' => [
                            ['node' => ['name' => 'Bone']],
                            ['node' => ['name' => 'Rope']],
                        ],
                    ],
                ],
            ],
        ], $data['dogs']['edges']);
    }

    public function testExposedClass(): void
    {
        $dog = new InheritanceDog('Rex', 'Labrador');

        $inheritance = (new Driver($this->getEntityManager(), new Config(['group' => 'Inheritance'])))
            ->get(EntityTypeContainer::class);
        $this->assertSame(InheritanceAnimal::class, $inheritance->getExposedClass($dog));

        $subclass = (new Driver($this->getEntityManager(), new Config(['group' => 'InheritanceSubclass'])))
            ->get(EntityTypeContainer::class);
        $this->assertSame(InheritanceDog::class, $subclass->getExposedClass($dog));

        // An entity of no exposed class gives its own class
        $this->assertSame(Artist::class, $inheritance->getExposedClass(new Artist()));
    }
}
