<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryAnimal;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryBird;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryCat;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryDog;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_column;
use function array_keys;

/**
 * Each entity class's own repository is read.  Doctrine does not give an
 * entity its parent entity's repository, so an exposed subclass must have a
 * repository which extends its parent's.
 */
class RepositoryComputedFieldInheritanceTest extends TestCase
{
    private Driver $driver;

    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();
        $entityManager->persist(new RepositoryAnimal('Generic'));
        $entityManager->persist(new RepositoryCat('Tom'));
        $entityManager->persist(new RepositoryDog('Rex'));
        $entityManager->persist(new RepositoryBird('Tweety'));
        $entityManager->flush();
        $entityManager->clear();

        $this->driver = new Driver($entityManager, new Config(['group' => 'RepositoryInheritance']));
    }

    public function testEachClassHasTheFieldsOfItsOwnRepository(): void
    {
        $metadata = $this->driver->get(Metadata::class)->toArray();

        $this->assertSame(['sound'], array_keys($metadata[RepositoryAnimal::class]['computedFields']));
        $this->assertSame(['lives', 'sound'], array_keys($metadata[RepositoryCat::class]['computedFields']));
        $this->assertSame(['sound'], array_keys($metadata[RepositoryDog::class]['computedFields']));
    }

    public function testEachRowIsGivenToTheRepositoryOfItsExposedClass(): void
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'animals' => $this->driver->completeConnection(RepositoryAnimal::class),
                    'cats' => $this->driver->completeConnection(RepositoryCat::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ animals { edges { node { name sound } } } cats { edges { node { name sound lives } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(
            [
                'RepositoryAnimalRepository: Generic',
                'RepositoryCatRepository: Tom',
                'RepositoryDogRepository: Rex',
                'RepositoryAnimalRepository: Tweety',
            ],
            array_column(array_column($result['data']['animals']['edges'], 'node'), 'sound'),
        );
        $this->assertSame(
            [['name' => 'Tom', 'sound' => 'RepositoryCatRepository: Tom', 'lives' => 9]],
            array_column($result['data']['cats']['edges'], 'node'),
        );
    }

    public function testAnExposedSubclassWithoutItsParentsRepositoryFieldsIsAnError(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Entity ' . RepositoryBird::class . ' extends ' . RepositoryAnimal::class . ', whose repository gives '
            . 'computed field sound, but the repository of ' . RepositoryBird::class . ' does not.  Doctrine does '
            . 'not give an entity its parent entity\'s repository: give ' . RepositoryBird::class . ' a repository '
            . 'which extends its parent\'s.',
        );

        (new Driver($this->getEntityManager(), new Config(['group' => 'RepositoryInheritanceMissing'])))
            ->get(Metadata::class);
    }
}
