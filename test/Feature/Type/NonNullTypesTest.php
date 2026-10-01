<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestNonNullTypes;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestNonNullTypesDetail;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * With useNonNullTypes, an entity type's identifier, the fields of columns
 * which are not nullable and the to-one associations whose join columns are
 * not nullable are non-null types
 */
class NonNullTypesTest extends TestCase
{
    private NonNull $requiredString;

    public function setUp(): void
    {
        parent::setUp();

        $this->requiredString = Type::nonNull(Type::string());

        $artist = $this->getEntityManager()->getRepository(Artist::class)->findOneBy(['name' => 'Grateful Dead']);
        $this->assertInstanceOf(Artist::class, $artist);

        $entity = new TestNonNullTypes($artist);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->persist(new TestNonNullTypesDetail($entity));
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }

    private function driver(bool $useNonNullTypes, bool $batchAssociations = true): Driver
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'NonNullTypes',
            'entityPrefix' => 'ApiSkeletonsTest\\Doctrine\\ORM\\GraphQL\\Entity\\',
            'useNonNullTypes' => $useNonNullTypes,
            'batchAssociations' => $batchAssociations,
        ]));
        $driver->get(TypeContainer::class)->set('requiredstring', fn () => $this->requiredString);

        return $driver;
    }

    private function fieldType(Driver $driver, string $entityClass, string $fieldName): Type
    {
        $objectType = $driver->type($entityClass);
        $this->assertInstanceOf(ObjectType::class, $objectType);

        return $objectType->getField($fieldName)->getType();
    }

    public function testOffByDefault(): void
    {
        $this->assertFalse((new Config())->getUseNonNullTypes());
        $this->assertFalse(ConfigBuilder::create()->build()->getUseNonNullTypes());

        $driver = $this->driver(false);

        foreach (['id', 'name', 'requiredArtist'] as $fieldName) {
            $this->assertNotInstanceOf(NonNull::class, $this->fieldType($driver, TestNonNullTypes::class, $fieldName));
        }

        $this->assertNotInstanceOf(NonNull::class, $this->fieldType($driver, TestNonNullTypesDetail::class, 'owner'));
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function fieldProvider(): array
    {
        // The field, and the name of its type: non-null types end with !
        return [
            'identifier' => [TestNonNullTypes::class, 'id', 'Int!'],
            'column which is not nullable' => [TestNonNullTypes::class, 'name', 'String!'],
            'nullable column' => [TestNonNullTypes::class, 'nickname', 'String'],
            'computed field' => [TestNonNullTypes::class, 'label', 'String'],
            'join column which is not nullable' => [TestNonNullTypes::class, 'requiredArtist', 'Artist_NonNullTypes!'],
            'nullable join column' => [TestNonNullTypes::class, 'optionalArtist', 'Artist_NonNullTypes'],
            'default join column' => [TestNonNullTypes::class, 'defaultArtist', 'Artist_NonNullTypes'],
            'inverse side of a one-to-one' => [TestNonNullTypes::class, 'detail', 'TestNonNullTypesDetail_NonNullTypes'],
            'owning side of a one-to-one' => [TestNonNullTypesDetail::class, 'owner', 'TestNonNullTypes_NonNullTypes!'],
        ];
    }

    #[DataProvider('fieldProvider')]
    public function testFieldType(string $entityClass, string $fieldName, string $typeName): void
    {
        $this->assertSame($typeName, $this->fieldType($this->driver(true), $entityClass, $fieldName)->toString());
    }

    public function testCustomNonNullTypeIsUnchanged(): void
    {
        $this->assertSame($this->requiredString, $this->fieldType($this->driver(true), TestNonNullTypes::class, 'code'));
        $this->assertSame($this->requiredString, $this->fieldType($this->driver(false), TestNonNullTypes::class, 'code'));
    }

    public function testCollectionIsNullable(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['useNonNullTypes' => true]));

        $this->assertInstanceOf(NonNull::class, $this->fieldType($driver, Artist::class, 'id'));
        $this->assertNotInstanceOf(NonNull::class, $this->fieldType($driver, Artist::class, 'performances'));
    }

    /** @return array<string, array{bool}> */
    public static function batchProvider(): array
    {
        return [
            'batched' => [true],
            'not batched' => [false],
        ];
    }

    #[DataProvider('batchProvider')]
    public function testQuery(bool $batchAssociations): void
    {
        $driver = $this->driver(true, $batchAssociations);
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'entities' => $driver->completeConnection(TestNonNullTypes::class),
                    'details' => $driver->completeConnection(TestNonNullTypesDetail::class),
                ],
            ]),
        ]);
        $schema->assertValid();

        $result = GraphQL::executeQuery($schema, '{
            entities { edges { node {
                id name nickname code label
                requiredArtist { name } optionalArtist { name } defaultArtist { name }
                detail { id }
            } } }
            details { edges { node { owner { name } } } }
        }')->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $node = $result['data']['entities']['edges'][0]['node'];
        $this->assertSame('required', $node['name']);
        $this->assertNull($node['nickname']);
        $this->assertSame('code', $node['code']);
        $this->assertSame('required', $node['label']);
        $this->assertSame(['name' => 'Grateful Dead'], $node['requiredArtist']);
        $this->assertNull($node['optionalArtist']);
        $this->assertNull($node['defaultArtist']);
        $this->assertSame(['id' => $node['id']], $node['detail']);
        $this->assertSame(['name' => 'required'], $result['data']['details']['edges'][0]['node']['owner']);
    }

    public function testBuilder(): void
    {
        $this->assertTrue(ConfigBuilder::create()->useNonNullTypes()->build()->getUseNonNullTypes());
        $this->assertFalse(ConfigBuilder::create()->useNonNullTypes(false)->build()->getUseNonNullTypes());
    }

    public function testInvalidValue(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration value for useNonNullTypes: expected bool, got int.');

        new Config(['useNonNullTypes' => 1]);
    }
}
