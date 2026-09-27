<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Hydrator\Strategy;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface;
use stdClass;

class AssociationDefaultTest extends TestCase
{
    public function testIsACollectionStrategy(): void
    {
        $this->assertInstanceOf(CollectionStrategyInterface::class, new AssociationDefault());
    }

    public function testExtractReturnsTheValue(): void
    {
        $value = new stdClass();

        $this->assertSame($value, (new AssociationDefault())->extract($value));
    }

    public function testCollectionStateIsReturnedOnceSet(): void
    {
        $strategy      = new AssociationDefault();
        $object        = new stdClass();
        $classMetadata = $this->getEntityManager()->getClassMetadata(Artist::class);

        $strategy->setCollectionName('performances');
        $strategy->setClassMetadata($classMetadata);
        $strategy->setObject($object);

        $this->assertSame('performances', $strategy->getCollectionName());
        $this->assertSame($classMetadata, $strategy->getClassMetadata());
        $this->assertSame($object, $strategy->getObject());
    }

    public function testCollectionNameMustBeSet(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Collection name has not been set.');

        (new AssociationDefault())->getCollectionName();
    }

    public function testClassMetadataMustBeSet(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Class metadata has not been set.');

        (new AssociationDefault())->getClassMetadata();
    }

    public function testObjectMustBeSet(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Object has not been set.');

        (new AssociationDefault())->getObject();
    }
}
