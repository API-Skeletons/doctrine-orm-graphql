<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use stdClass;

use function strlen;
use function strtoupper;

class DoctrineObjectWithComputedTest extends TestCase
{
    private DoctrineObjectWithComputed $hydrator;

    protected function setUp(): void
    {
        // Create a stub entity manager for testing (not used by the hydrator methods we're testing)
        $entityManager = $this->createStub(EntityManager::class);

        // DoctrineObjectWithComputed extends DoctrineObject which takes EntityManager and byValue flag
        $this->hydrator = new DoctrineObjectWithComputed($entityManager, false);
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
        $entity       = new stdClass();
        $entity->name = 'Test Name';

        // Add computed field that transforms the name
        $this->hydrator->addComputedField('uppercaseName', static fn ($obj) => strtoupper($obj->name));
        $this->hydrator->addComputedField('nameLength', static fn ($obj) => strlen($obj->name));

        $result = $this->hydrator->extract($entity);

        $this->assertArrayHasKey('uppercaseName', $result);
        $this->assertArrayHasKey('nameLength', $result);
        $this->assertEquals('TEST NAME', $result['uppercaseName']);
        $this->assertEquals(9, $result['nameLength']);
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
