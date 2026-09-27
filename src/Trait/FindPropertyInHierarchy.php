<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use ReflectionClass;
use ReflectionProperty;

/**
 * Find a property declared on a class or any of its parent classes
 *
 * ReflectionClass::getProperty() does not return a private property declared
 * on a parent class, such as a mapped superclass or a parent entity.
 */
trait FindPropertyInHierarchy
{
    /**
     * Doctrine's ClassMetadata returns ReflectionClass<covariant T>.  PHPStan
     * needs the call-site variance to accept it; Psalm cannot parse it.
     *
     * @param ReflectionClass<object> $reflectionClass
     * @psalm-param ReflectionClass<object> $reflectionClass
     * @phpstan-param ReflectionClass<covariant object> $reflectionClass
     */
    private function findPropertyInHierarchy(
        ReflectionClass $reflectionClass,
        string $propertyName,
    ): ReflectionProperty|null {
        for ($class = $reflectionClass; $class !== false; $class = $class->getParentClass()) {
            if ($class->hasProperty($propertyName)) {
                return $class->getProperty($propertyName);
            }
        }

        return null;
    }
}
