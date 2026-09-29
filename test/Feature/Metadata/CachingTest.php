<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Error\Error;
use PHPUnit\Framework\Attributes\DataProvider;

class CachingTest extends TestCase
{
    public function testCacheMetadata(): void
    {
        $driver = new Driver($this->getEntityManager());

        $metadata = $driver->get(Metadata::class);

        unset($driver);

        $driver = new Driver($this->getEntityManager(), null, $metadata->toArray());
        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeContainer::class)->get(User::class));
    }

    public function testStaticMetadata(): void
    {
        $driver            = new Driver($this->getEntityManager(), new Config(['group' => 'StaticMetadata']));
        $generatedMetadata = $driver->get(Metadata::class)->toArray();

        $metadata = [
            '__version' => Metadata::FORMAT_VERSION,
            '__config' => [
                'group' => 'StaticMetadata',
                'groupSuffix' => null,
                'entityPrefix' => null,
                'extractByValue' => null,
            ],
            'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User' => [
                'entityClass' => 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User',
                'extractByValue' => true,
                'limit' => 0,
                'description' => '',
                'excludeFilters' => [],
                'typeName' => 'ApiSkeletonsTest_Doctrine_ORM_GraphQL_Entity_User_StaticMetadata',
                'fields' => [
                    'name' => [
                        'alias' => null,
                        'description' => null,
                        'type' => 'string',
                        'hydratorStrategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                        'excludeFilters' => [],
                    ],
                    'recordings' => [
                        'alias' => null,
                        'limit' => null,
                        'description' => null,
                        'eventName' => null,
                        'hydratorStrategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
                        'excludeFilters' => ['eq'],
                    ],
                ],
            ],
        ];

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'StaticMetadata']), $metadata);

        $this->assertEquals($generatedMetadata, $metadata);
        $this->assertEquals($generatedMetadata, $driver->get(Metadata::class)->toArray());

        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeContainer::class)->get(User::class));

        $this->expectException(Error::class);
        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeContainer::class)->get(Artist::class));
    }

    public function testExportedMetadataCarriesTheFormatVersion(): void
    {
        $driver = new Driver($this->getEntityManager());

        $exported = $driver->get(Metadata::class)->toArray();

        $this->assertSame(Metadata::FORMAT_VERSION, $exported['__version']);
        $this->assertArrayHasKey(User::class, $exported);

        // The version is part of the exported form only, not of the metadata
        $this->assertArrayNotHasKey('__version', $driver->get(Metadata::class)->getArrayCopy());
    }

    public function testCachedMetadataWithoutAVersionIsRejected(): void
    {
        $metadata = (new Driver($this->getEntityManager()))->get(Metadata::class)->getArrayCopy();

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('has no format version');

        new Driver($this->getEntityManager(), null, $metadata);
    }

    public function testCachedMetadataWithAnotherVersionIsRejected(): void
    {
        $metadata              = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();
        $metadata['__version'] = Metadata::FORMAT_VERSION + 1;

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('format version ' . (Metadata::FORMAT_VERSION + 1));

        new Driver($this->getEntityManager(), null, $metadata);
    }

    /**
     * Metadata depends on the group, group suffix, entity prefix and
     * extractByValue; a cache built with other values is rejected
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function otherConfigProvider(): array
    {
        return [
            'group' => [['group' => 'LimitTest'], "group 'default' rather than 'LimitTest'"],
            'groupSuffix' => [['groupSuffix' => 'v2'], "groupSuffix NULL rather than 'v2'"],
            'entityPrefix' => [
                ['entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\'],
                "entityPrefix NULL rather than 'ApiSkeletonsTest",
            ],
            'extractByValue' => [['extractByValue' => false], 'extractByValue NULL rather than false'],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('otherConfigProvider')]
    public function testCachedMetadataBuiltWithAnotherConfigIsRejected(array $config, string $message): void
    {
        $metadata = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();
        $driver   = new Driver($this->getEntityManager(), new Config($config), $metadata);

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Cached metadata was built with ' . $message);

        $driver->get(Metadata::class);
    }

    public function testExportedMetadataCarriesItsConfig(): void
    {
        $config   = new Config(['group' => 'LimitTest', 'groupSuffix' => 'v2', 'extractByValue' => true]);
        $exported = (new Driver($this->getEntityManager(), $config))->get(Metadata::class)->toArray();

        $this->assertSame(
            ['group' => 'LimitTest', 'groupSuffix' => 'v2', 'entityPrefix' => null, 'extractByValue' => true],
            $exported['__config'],
        );

        // It is read back with the same config
        $driver = new Driver($this->getEntityManager(), $config, $exported);
        $this->assertSame($exported, $driver->get(Metadata::class)->toArray());
    }

    /** @return array<string, array{mixed}> */
    public static function invalidConfigKeyProvider(): array
    {
        return [
            'missing' => [null],
            'not an array' => ['default'],
            'no group' => [['groupSuffix' => null, 'entityPrefix' => null, 'extractByValue' => null]],
            'missing key' => [['group' => 'default', 'groupSuffix' => null, 'entityPrefix' => null]],
            'wrong type' => [['group' => 'default', 'groupSuffix' => 1, 'entityPrefix' => null, 'extractByValue' => null]],
        ];
    }

    #[DataProvider('invalidConfigKeyProvider')]
    public function testCachedMetadataWithoutAValidConfigIsRejected(mixed $builtWith): void
    {
        $metadata = (new Driver($this->getEntityManager()))->get(Metadata::class)->toArray();

        if ($builtWith === null) {
            unset($metadata['__config']);
        } else {
            $metadata['__config'] = $builtWith;
        }

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('has no valid __config key');

        new Driver($this->getEntityManager(), null, $metadata);
    }

    public function testMetadataNotBuiltByADriverCannotBeExported(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('was not built by a driver');

        (new Metadata())->toArray();
    }
}
