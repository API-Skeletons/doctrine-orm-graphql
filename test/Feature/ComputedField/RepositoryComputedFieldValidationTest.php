<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryCompositeKey;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryDerivedKey;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\RepositoryInvalid;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryArtistRepository;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryInvalidRepository;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Repository\RepositoryKeyRepository;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

/**
 * A computed field of a repository is checked when the metadata is built
 */
class RepositoryComputedFieldValidationTest extends TestCase
{
    private const string REPOSITORY = ' of repository ' . RepositoryInvalidRepository::class
        . ' of entity ' . RepositoryInvalid::class;

    /** @return array<string, array{string, string}> */
    public static function invalidProvider(): array
    {
        $entity = ', whose first parameter must accept the entity.';
        $batch  = ', whose first parameter must accept any ' . Collection::class . ' of entities.';

        return [
            'another entity' => [
                'RepositoryWrongEntity',
                'Computed field "wrongEntity"' . self::REPOSITORY . ' is of method wrongEntity' . $entity,
            ],
            'no parameter' => [
                'RepositoryNoParameter',
                'Computed field "noParameter"' . self::REPOSITORY . ' is of method noParameter' . $entity,
            ],
            'a batch given an ArrayCollection' => [
                'RepositoryBatchArrayCollection',
                'Computed field "batchArrayCollection"' . self::REPOSITORY . ' is of method batchArrayCollection'
                    . $batch,
            ],
            'a batch given an array' => [
                'RepositoryBatchArray',
                'Computed field "batchArray"' . self::REPOSITORY . ' is of method batchArray' . $batch,
            ],
            'an intersection or another entity' => [
                'RepositoryIntersection',
                'Computed field "intersection"' . self::REPOSITORY . ' is of method intersection' . $entity,
            ],
            'a scalar' => [
                'RepositoryBuiltin',
                'Computed field "builtin"' . self::REPOSITORY . ' is of method builtin' . $entity,
            ],
            'a method which is not public' => [
                'RepositoryPrivate',
                'Method hidden' . self::REPOSITORY . ' has a ComputedField attribute but is not a public, '
                    . 'non-static method.',
            ],
            'an argument without a type' => [
                'RepositoryArgs',
                'Parameter $since of computed field method since' . self::REPOSITORY . ' is not an int, float, '
                    . 'string or bool.  Give its type in the args of the ComputedField attribute.',
            ],
            'batch on a method of an entity' => [
                'RepositoryBatchOnEntity',
                'Computed field "count" of entity ' . RepositoryInvalid::class . ' is batched, but only a method '
                    . 'of a repository is given a Collection of entities.',
            ],
            'batch with a composite identifier' => [
                'RepositoryBatchKey',
                'Computed field "count" of repository ' . RepositoryKeyRepository::class . ' of entity '
                    . RepositoryCompositeKey::class . ' is batched, but the entity\'s identifier is composite or an '
                    . 'association, which cannot key its entities.',
            ],
            'batch with an association as the identifier' => [
                'RepositoryBatchDerivedKey',
                'Computed field "count" of repository ' . RepositoryKeyRepository::class . ' of entity '
                    . RepositoryDerivedKey::class . ' is batched, but the entity\'s identifier is composite or an '
                    . 'association, which cannot key its entities.',
            ],
            'the name of a computed field of the entity' => [
                'RepositoryCollision',
                'Computed field "displayName" of method getDisplayName of repository '
                    . RepositoryArtistRepository::class . ' of entity ' . RepositoryArtist::class
                    . ' collides with another computed field of the same name.',
            ],
            'the name of a field' => [
                'RepositoryFieldCollision',
                'Computed field "name" collides with existing field in entity ' . RepositoryArtist::class,
            ],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalid(string $group, string $message): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage($message);

        (new Driver($this->getEntityManager(), new Config(['group' => $group])))->get(Metadata::class);
    }

    public function testAccepted(): void
    {
        $metadata = (new Driver($this->getEntityManager(), new Config(['group' => 'RepositoryAccepted'])))
            ->get(Metadata::class)
            ->toArray();

        $computedFields = $metadata[RepositoryInvalid::class]['computedFields'];

        $this->assertSame(
            ['untyped', 'mixedEntity', 'union', 'iterableBatch', 'traversableBatch'],
            array_keys($computedFields),
        );
        $this->assertTrue($computedFields['traversableBatch']['batch']);
    }
}
