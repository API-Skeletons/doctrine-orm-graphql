<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Album;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\EntityFormerByValue\FormerByValue;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

use function dirname;
use function method_exists;

class ExtractByValueTest extends TestCase
{
    #[IgnoreDeprecations]
    public function testExtractByValueFalse(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['extractByValue' => false]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertFalse($driver->get(Config::class)->getExtractByValue());
    }

    /**
     * The extractByValue config option overrides the extractByValue argument
     * of every entity's attribute.  Album is extracted by reference in this
     * group.
     */
    public function testExtractByValueOverridesTheEntityAttribute(): void
    {
        $group = 'MappedSuperclassByReferenceTest';

        $byAttribute = (new Driver($this->getEntityManager(), new Config(['group' => $group])))
            ->get(Metadata::class)[Album::class]['extractByValue'];
        $byValue     = (new Driver($this->getEntityManager(), new Config(['group' => $group, 'extractByValue' => true])))
            ->get(Metadata::class)[Album::class]['extractByValue'];
        $byReference = (new Driver($this->getEntityManager(), new Config(['group' => 'default', 'extractByValue' => false])))
            ->get(Metadata::class)[Artist::class]['extractByValue'];

        $this->assertFalse($byAttribute);
        $this->assertTrue($byValue);
        $this->assertFalse($byReference);
    }

    public function testTheFormerNameIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration setting: globalByValue');

        new Config(['globalByValue' => true]);
    }

    /**
     * The Entity attribute's former byValue argument is named in the error
     * rather than reported as an unknown named parameter
     */
    public function testTheFormerAttributeArgumentIsRejected(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2) . '/EntityFormerByValue'],
            isDevMode: true,
        );
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'The byValue argument of the Entity attribute of ' . FormerByValue::class . ' is renamed extractByValue.',
        );

        (new Driver($entityManager))->get(Metadata::class);
    }
}
