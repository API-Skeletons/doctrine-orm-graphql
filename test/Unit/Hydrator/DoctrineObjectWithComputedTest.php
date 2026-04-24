<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

use function strlen;
use function strtoupper;

class DoctrineObjectWithComputedTest extends TestCase
{
    private DoctrineObjectWithComputed $hydrator;

    public function setUp(): void
    {
        parent::setUp();

        $this->hydrator = new DoctrineObjectWithComputed($this->getEntityManager(), false);
    }

    public function testHasComputedFieldReturnsFalseWhenNotRegistered(): void
    {
        $this->assertFalse($this->hydrator->hasComputedField('nonExistentField'));
    }

    public function testHasComputedFieldReturnsTrueWhenRegistered(): void
    {
        $this->hydrator->addComputedField('testField', static fn ($entity) => 'value');

        $this->assertTrue($this->hydrator->hasComputedField('testField'));
    }

    public function testGetComputedFieldNamesReturnsEmptyArrayWhenNoFieldsRegistered(): void
    {
        $this->assertSame([], $this->hydrator->getComputedFieldNames());
    }

    public function testGetComputedFieldNamesReturnsAllRegisteredFields(): void
    {
        $this->hydrator->addComputedField('field1', static fn ($entity) => 'value1');
        $this->hydrator->addComputedField('field2', static fn ($entity) => 'value2');
        $this->hydrator->addComputedField('field3', static fn ($entity) => 'value3');

        $fieldNames = $this->hydrator->getComputedFieldNames();

        $this->assertCount(3, $fieldNames);
        $this->assertContains('field1', $fieldNames);
        $this->assertContains('field2', $fieldNames);
        $this->assertContains('field3', $fieldNames);
    }

    public function testExtractIncludesComputedFields(): void
    {
        $artist = $this->getEntityManager()
            ->getRepository(Artist::class)
            ->findOneBy(['name' => 'Grateful Dead']);

        $this->hydrator->addComputedField('uppercaseName', static fn ($obj) => strtoupper($obj->getName()));
        $this->hydrator->addComputedField('nameLength', static fn ($obj) => strlen($obj->getName()));

        $result = $this->hydrator->extract($artist);

        $this->assertArrayHasKey('uppercaseName', $result);
        $this->assertArrayHasKey('nameLength', $result);
        $this->assertEquals('GRATEFUL DEAD', $result['uppercaseName']);
        $this->assertEquals(13, $result['nameLength']);
    }

    public function testAddComputedFieldAllowsMultipleFields(): void
    {
        $this->hydrator->addComputedField('field1', static fn ($entity) => 'value1');
        $this->hydrator->addComputedField('field2', static fn ($entity) => 'value2');

        $this->assertTrue($this->hydrator->hasComputedField('field1'));
        $this->assertTrue($this->hydrator->hasComputedField('field2'));
        $this->assertCount(2, $this->hydrator->getComputedFieldNames());
    }
}
