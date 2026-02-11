<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithCollision;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithGetMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithIsMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithPlainMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use RuntimeException;

class ComputedFieldEdgeCasesTest extends TestCase
{
    public function testComputedFieldNameCollisionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Computed field "name" collides with existing field in entity');

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'collisionTest']));

        // This should throw an exception because 'name' collides with the field
        // The exception is thrown when the type is accessed (lazy evaluation)
        $driver->type(TestEntityWithCollision::class);
    }

    public function testDeriveFieldNameFromMethodWithoutGetPrefix(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'plainMethodTest']));

        $graphQLType = $driver->type(TestEntityWithPlainMethod::class);
        $fields      = $graphQLType->getFields();

        // Method name without 'get' or 'is' prefix should use method name as-is
        $this->assertArrayHasKey('calculate', $fields);
        $this->assertEquals('String', (string) $fields['calculate']->getType());
    }

    public function testDeriveFieldNameFromIsMethodKeepsName(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'isMethodTest2']));

        $graphQLType = $driver->type(TestEntityWithIsMethod::class);
        $fields      = $graphQLType->getFields();

        // isXxx() methods should keep their name
        $this->assertArrayHasKey('isValid', $fields);
        $this->assertEquals('Boolean', (string) $fields['isValid']->getType());
    }

    public function testDeriveFieldNameFromGetMethodRemovesPrefix(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'getMethodTest']));

        $graphQLType = $driver->type(TestEntityWithGetMethod::class);
        $fields      = $graphQLType->getFields();

        // getXxx() methods should have 'get' prefix removed and first letter lowercased
        $this->assertArrayHasKey('displayValue', $fields);
        $this->assertEquals('String', (string) $fields['displayValue']->getType());
    }
}
