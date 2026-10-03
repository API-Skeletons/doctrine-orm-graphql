<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigTest extends TestCase
{
    public function testInvalidConfig(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(['invalid' => 'invalid']);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidValueProvider(): array
    {
        return [
            'string limit' => [['limit' => '5'], 'Invalid configuration value for limit: expected int, got string.'],
            'zero limit' => [['limit' => 0], 'Invalid configuration value for limit: it must be at least 1, got 0.'],
            'negative limit' => [['limit' => -1], 'Invalid configuration value for limit: it must be at least 1, got -1.'],
            'negative batchLimit' => [
                ['batchLimit' => -5],
                'Invalid configuration value for batchLimit: it must be at least 0, got -5.',
            ],
            'int group' => [['group' => 5], 'Invalid configuration value for group: expected string, got int.'],
            'empty group' => [['group' => ''], 'Invalid configuration value for group: it may not be empty.'],
            'string bool' => [
                ['useHydratorCache' => 'yes'],
                'Invalid configuration value for useHydratorCache: expected bool, got string.',
            ],
            'int nullable bool' => [
                ['extractByValue' => 1],
                'Invalid configuration value for extractByValue: expected bool or null, got int.',
            ],
            'null sortFields' => [
                ['sortFields' => null],
                'Invalid configuration value for sortFields: expected bool, got null.',
            ],
            'excludeFilters not an array' => [
                ['excludeFilters' => 'eq'],
                'Invalid configuration value for excludeFilters: expected array, got string.',
            ],
            'unknown filter' => [
                ['excludeFilters' => ['equals']],
                'Invalid configuration value for excludeFilters: "equals" is not a filter.',
            ],
            'filter of another type' => [
                ['excludeFilters' => [1]],
                'Invalid configuration value for excludeFilters: int is not a filter.',
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('invalidValueProvider')]
    public function testInvalidValueIsRejected(array $config, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        new Config($config);
    }

    public function testBuilderValuesAreValidated(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration value for limit: it must be at least 1, got 0.');

        ConfigBuilder::create()->withLimit(0)->build();
    }

    public function testLowestValuesAreAccepted(): void
    {
        $config = new Config(['limit' => 1, 'batchLimit' => 0]);

        $this->assertSame(1, $config->getLimit());
        $this->assertSame(0, $config->getBatchLimit());
    }

    /**
     * A filter may be given by its value; it is returned as a Filters case
     */
    public function testExcludeFiltersAreFilters(): void
    {
        $config = new Config(['excludeFilters' => ['eq', Filters::NEQ]]);

        $this->assertSame([Filters::EQ, Filters::NEQ], $config->getExcludeFilters());
    }
}
