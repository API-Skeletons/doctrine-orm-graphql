<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Exception;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\GraphQL as GraphQLException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input as InputException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound as TypeNotFoundException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Error\Error;

class ExceptionTest extends TestCase
{
    public function testGraphQLExceptionExtendsGraphQLError(): void
    {
        $exception = new GraphQLException('Test message');

        $this->assertInstanceOf(Error::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testTypeNotFoundExceptionBasicMessage(): void
    {
        $exception = new TypeNotFoundException(
            typeId: 'NonExistentType',
        );

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertStringContainsString('Type "NonExistentType" is not registered', $exception->getMessage());
    }

    public function testTypeNotFoundExceptionWithSuggestion(): void
    {
        $exception = new TypeNotFoundException(
            typeId: 'Artistt',
            suggestion: 'Artist',
        );

        $this->assertStringContainsString('Type "Artistt" is not registered', $exception->getMessage());
        $this->assertStringContainsString('Did you mean "Artist"?', $exception->getMessage());
    }

    public function testTypeNotFoundExceptionWithAvailableTypes(): void
    {
        $exception = new TypeNotFoundException(
            typeId: 'Custom',
            availableTypes: ['Artist', 'Performance', 'Recording'],
        );

        $this->assertStringContainsString('Type "Custom" is not registered', $exception->getMessage());
        $this->assertStringContainsString('Available types:', $exception->getMessage());
        $this->assertStringContainsString('Artist', $exception->getMessage());
        $this->assertStringContainsString('Performance', $exception->getMessage());
        $this->assertStringContainsString('Recording', $exception->getMessage());
    }

    public function testTypeNotFoundExceptionWithSuggestionAndAvailableTypes(): void
    {
        $exception = new TypeNotFoundException(
            typeId: 'Performence',
            availableTypes: ['Artist', 'Performance', 'Recording'],
            suggestion: 'Performance',
        );

        $this->assertStringContainsString('Type "Performence" is not registered', $exception->getMessage());
        $this->assertStringContainsString('Did you mean "Performance"?', $exception->getMessage());
        $this->assertStringContainsString('Available types:', $exception->getMessage());
    }

    public function testTypeSerializationExceptionBasicMessage(): void
    {
        $exception = new TypeSerializationException('Invalid date format');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertEquals('Invalid date format', $exception->getMessage());
    }

    public function testConfigurationExceptionBasicMessage(): void
    {
        $exception = new ConfigurationException('Invalid configuration setting: foo');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertStringContainsString('Invalid configuration setting: foo', $exception->getMessage());
    }

    public function testConfigurationExceptionWithValidOptions(): void
    {
        $exception = new ConfigurationException(
            'Invalid configuration setting: badOption',
            ['group', 'limit', 'globalEnable'],
        );

        $this->assertStringContainsString('Invalid configuration setting: badOption', $exception->getMessage());
        $this->assertStringContainsString('Valid options:', $exception->getMessage());
        $this->assertStringContainsString('group', $exception->getMessage());
        $this->assertStringContainsString('limit', $exception->getMessage());
        $this->assertStringContainsString('globalEnable', $exception->getMessage());
    }

    public function testMetadataExceptionBasicMessage(): void
    {
        $exception = new MetadataException('Entity not found in metadata');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertEquals('Entity not found in metadata', $exception->getMessage());
    }

    public function testHydratorExceptionBasicMessage(): void
    {
        $exception = new HydratorException('Collection name has not been set');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertEquals('Collection name has not been set', $exception->getMessage());
    }

    public function testFilterExceptionBasicMessage(): void
    {
        $exception = new FilterException('Sort direction not set');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertEquals('Sort direction not set', $exception->getMessage());
    }

    public function testInputExceptionBasicMessage(): void
    {
        $exception = new InputException('Identifier id is an invalid input');

        $this->assertInstanceOf(GraphQLException::class, $exception);
        $this->assertEquals('Identifier id is an invalid input', $exception->getMessage());
    }

    public function testExceptionsAreThrowable(): void
    {
        $this->expectException(GraphQLException::class);

        throw new GraphQLException('Test throwable');
    }

    public function testTypeNotFoundExceptionIsThrowable(): void
    {
        $this->expectException(TypeNotFoundException::class);
        $this->expectExceptionMessage('Type "test" is not registered');

        throw new TypeNotFoundException(typeId: 'test');
    }

    public function testConfigurationExceptionIsThrowable(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid setting');

        throw new ConfigurationException('Invalid setting');
    }

    public function testMetadataExceptionIsThrowable(): void
    {
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage('Metadata error');

        throw new MetadataException('Metadata error');
    }

    public function testHydratorExceptionIsThrowable(): void
    {
        $this->expectException(HydratorException::class);
        $this->expectExceptionMessage('Hydrator error');

        throw new HydratorException('Hydrator error');
    }

    public function testFilterExceptionIsThrowable(): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('Filter error');

        throw new FilterException('Filter error');
    }

    public function testInputExceptionIsThrowable(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Input error');

        throw new InputException('Input error');
    }

    public function testTypeSerializationExceptionIsThrowable(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Serialization error');

        throw new TypeSerializationException('Serialization error');
    }
}
