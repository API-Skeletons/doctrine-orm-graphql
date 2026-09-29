<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\MetadataFactory;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithCollision;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithGetMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithIsMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithPlainMethod;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;

class ComputedFieldEdgeCasesTest extends TestCase
{
    public function testComputedFieldNameCollisionThrowsException(): void
    {
        $this->expectException(MetadataException::class);
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

    /**
     * A get prefix is removed only before an uppercase letter
     *
     * @return array<string, array{string, string}>
     */
    public static function methodNameProvider(): array
    {
        return [
            'getter' => ['getFullName', 'fullName'],
            'one letter' => ['getX', 'x'],
            'word beginning get' => ['getaway', 'getaway'],
            'get alone' => ['get', 'get'],
            'isser' => ['isActive', 'isActive'],
            'word beginning is' => ['island', 'island'],
            'other' => ['fullName', 'fullName'],
        ];
    }

    #[DataProvider('methodNameProvider')]
    public function testFieldNameIsDerivedFromTheMethodName(string $methodName, string $fieldName): void
    {
        $factory = (new ReflectionClass(MetadataFactory::class))->newInstanceWithoutConstructor();

        $this->assertSame($fieldName, (new ReflectionMethod($factory, 'deriveFieldNameFromMethod'))->invoke($factory, $methodName));
    }
}
