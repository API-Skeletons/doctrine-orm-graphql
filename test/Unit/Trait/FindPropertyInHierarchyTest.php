<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Trait;

use ApiSkeletons\Doctrine\ORM\GraphQL\Trait\FindPropertyInHierarchy;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\AbstractRelease;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Album;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class FindPropertyInHierarchyTest extends TestCase
{
    private object $finder;

    public function setUp(): void
    {
        $this->finder = new class {
            use FindPropertyInHierarchy;

            /** @param ReflectionClass<object> $reflectionClass */
            public function find(ReflectionClass $reflectionClass, string $propertyName): ReflectionProperty|null
            {
                return $this->findPropertyInHierarchy($reflectionClass, $propertyName);
            }
        };
    }

    public function testFindsPropertyDeclaredOnTheClass(): void
    {
        $property = $this->finder->find(new ReflectionClass(Album::class), 'trackCount');

        $this->assertSame(Album::class, $property?->getDeclaringClass()->getName());
    }

    public function testFindsPrivatePropertyDeclaredOnAParentClass(): void
    {
        $this->assertFalse((new ReflectionClass(Album::class))->hasProperty('title'));

        $property = $this->finder->find(new ReflectionClass(Album::class), 'title');

        $this->assertSame(AbstractRelease::class, $property?->getDeclaringClass()->getName());
    }

    public function testReturnsNullForAnUndeclaredProperty(): void
    {
        $this->assertNull($this->finder->find(new ReflectionClass(Album::class), 'undeclared'));
    }
}
