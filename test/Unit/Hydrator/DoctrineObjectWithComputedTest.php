<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithMagicCall;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithMagicCallAndFilterProvider;
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

    /**
     * When an entity has no __call method, extractByValue returns early after
     * the parent extraction — the __call fallback loop is never entered.
     */
    public function testExtractByValueEarlyReturnWhenNoMagicCall(): void
    {
        $hydrator = new DoctrineObjectWithComputed($this->getEntityManager(), true);

        $artist = $this->getEntityManager()
            ->getRepository(Artist::class)
            ->findOneBy(['name' => 'Grateful Dead']);

        $result = $hydrator->extract($artist);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('Grateful Dead', $result['name']);
    }

    /**
     * When magicCall is false (default), __call is never used even when the
     * entity implements it — fields without an explicit getter are simply absent.
     */
    public function testExtractByValueDoesNotUseMagicCallWhenDisabled(): void
    {
        $em       = $this->getEntityManager();
        $hydrator = new DoctrineObjectWithComputed($em, true, false);

        $entity = (new TestEntityWithMagicCall())
            ->setRegularField('regular value')
            ->setMagicField('magic value');
        $em->persist($entity);
        $em->flush();
        $em->clear();

        $persisted = $em->getRepository(TestEntityWithMagicCall::class)->findAll()[0];

        $result = $hydrator->extract($persisted);

        $this->assertArrayHasKey('regularField', $result);
        $this->assertArrayNotHasKey('magicField', $result);
    }

    /**
     * When an entity implements __call and a field has no explicit getter,
     * extractByValue must invoke the getter via __call to populate the field.
     */
    public function testExtractByValueInvokesMagicCallForFieldsWithoutGetter(): void
    {
        $em       = $this->getEntityManager();
        $hydrator = new DoctrineObjectWithComputed($em, true, true);

        $entity = (new TestEntityWithMagicCall())
            ->setRegularField('regular value')
            ->setMagicField('magic value');
        $em->persist($entity);
        $em->flush();
        $em->clear();

        $persisted = $em->getRepository(TestEntityWithMagicCall::class)->findAll()[0];

        $result = $hydrator->extract($persisted);

        // regularField has an explicit getter — parent extracts it
        $this->assertArrayHasKey('regularField', $result);
        $this->assertEquals('regular value', $result['regularField']);

        // magicField has no explicit getter — extracted via __call
        $this->assertArrayHasKey('magicField', $result);
        $this->assertEquals('magic value', $result['magicField']);
    }

    /**
     * When a Laminas filter is attached to the hydrator, fields rejected by
     * the filter must be skipped even when __call would otherwise handle them.
     */
    public function testExtractByValueFiltersOutMagicCallFieldWhenFilterRejects(): void
    {
        $em       = $this->getEntityManager();
        $hydrator = new DoctrineObjectWithComputed($em, true, true);

        // Reject magicField; allow everything else
        $hydrator->addFilter(
            'blockMagicField',
            static fn (string $property): bool => $property !== 'magicField',
        );

        $entity = (new TestEntityWithMagicCall())
            ->setRegularField('regular value')
            ->setMagicField('should be filtered');
        $em->persist($entity);
        $em->flush();
        $em->clear();

        $persisted = $em->getRepository(TestEntityWithMagicCall::class)->findAll()[0];

        $result = $hydrator->extract($persisted);

        $this->assertArrayHasKey('regularField', $result);
        $this->assertArrayNotHasKey('magicField', $result);
    }

    /**
     * When an entity implements FilterProviderInterface, extractByValue must
     * call $object->getFilter() (line 83) rather than reading $this->filterComposite.
     * The entity's own filter allows all fields, so magicField is still extracted via __call.
     */
    public function testExtractByValueUsesEntityFilterWhenFilterProviderImplemented(): void
    {
        $em       = $this->getEntityManager();
        $hydrator = new DoctrineObjectWithComputed($em, true, true);

        $entity = (new TestEntityWithMagicCallAndFilterProvider())
            ->setRegularField('regular value')
            ->setMagicField('magic value');
        $em->persist($entity);
        $em->flush();
        $em->clear();

        $persisted = $em->getRepository(TestEntityWithMagicCallAndFilterProvider::class)->findAll()[0];

        $result = $hydrator->extract($persisted);

        $this->assertArrayHasKey('regularField', $result);
        $this->assertEquals('regular value', $result['regularField']);

        $this->assertArrayHasKey('magicField', $result);
        $this->assertEquals('magic value', $result['magicField']);
    }
}
