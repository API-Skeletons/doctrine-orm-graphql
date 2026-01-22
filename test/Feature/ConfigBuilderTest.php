<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\ConfigBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

class ConfigBuilderTest extends TestCase
{
    public function testCreateReturnsBuilderInstance(): void
    {
        $builder = ConfigBuilder::create();

        $this->assertInstanceOf(ConfigBuilder::class, $builder);
    }

    public function testBuildReturnsConfigInstance(): void
    {
        $config = ConfigBuilder::create()->build();

        $this->assertInstanceOf(Config::class, $config);
    }

    public function testWithGroupSetsGroup(): void
    {
        $config = ConfigBuilder::create()
            ->withGroup('test-group')
            ->build();

        $this->assertEquals('test-group', $config->getGroup());
    }

    public function testWithGroupSuffixSetsGroupSuffix(): void
    {
        $config = ConfigBuilder::create()
            ->withGroupSuffix('TestSuffix')
            ->build();

        $this->assertEquals('TestSuffix', $config->getGroupSuffix());
    }

    public function testWithGroupSuffixNullSetsNull(): void
    {
        $config = ConfigBuilder::create()
            ->withGroupSuffix(null)
            ->build();

        $this->assertNull($config->getGroupSuffix());
    }

    public function testUseHydratorCacheEnablesCache(): void
    {
        $config = ConfigBuilder::create()
            ->useHydratorCache()
            ->build();

        $this->assertTrue($config->getUseHydratorCache());
    }

    public function testUseHydratorCacheWithFalseDisablesCache(): void
    {
        $config = ConfigBuilder::create()
            ->useHydratorCache(false)
            ->build();

        $this->assertFalse($config->getUseHydratorCache());
    }

    public function testUseQueryResultCacheEnablesCache(): void
    {
        $config = ConfigBuilder::create()
            ->useQueryResultCache()
            ->build();

        $this->assertTrue($config->getUseQueryResultCache());
    }

    public function testUseQueryResultCacheWithFalseDisablesCache(): void
    {
        $config = ConfigBuilder::create()
            ->useQueryResultCache(false)
            ->build();

        $this->assertFalse($config->getUseQueryResultCache());
    }

    public function testWithLimitSetsLimit(): void
    {
        $config = ConfigBuilder::create()
            ->withLimit(500)
            ->build();

        $this->assertEquals(500, $config->getLimit());
    }

    public function testGlobalEnableEnablesGlobalEnable(): void
    {
        $config = ConfigBuilder::create()
            ->globalEnable()
            ->build();

        $this->assertTrue($config->getGlobalEnable());
    }

    public function testGlobalEnableWithFalseDisablesGlobalEnable(): void
    {
        $config = ConfigBuilder::create()
            ->globalEnable(false)
            ->build();

        $this->assertFalse($config->getGlobalEnable());
    }

    public function testIgnoreFieldsSetsFields(): void
    {
        $fields = ['field1', 'field2', 'field3'];
        $config = ConfigBuilder::create()
            ->ignoreFields($fields)
            ->build();

        $this->assertEquals($fields, $config->getIgnoreFields());
    }

    public function testIgnoreFieldAddsField(): void
    {
        $config = ConfigBuilder::create()
            ->ignoreField('field1')
            ->ignoreField('field2')
            ->build();

        $this->assertEquals(['field1', 'field2'], $config->getIgnoreFields());
    }

    public function testExtractByValueSetsGlobalByValue(): void
    {
        $config = ConfigBuilder::create()
            ->extractByValue()
            ->build();

        $this->assertTrue($config->getGlobalByValue());
    }

    public function testExtractByValueWithFalseSetsGlobalByValue(): void
    {
        $config = ConfigBuilder::create()
            ->extractByValue(false)
            ->build();

        $this->assertFalse($config->getGlobalByValue());
    }

    public function testExtractByReferenceSetsGlobalByValue(): void
    {
        $config = ConfigBuilder::create()
            ->extractByReference()
            ->build();

        $this->assertFalse($config->getGlobalByValue());
    }

    public function testWithEntityPrefixSetsEntityPrefix(): void
    {
        $config = ConfigBuilder::create()
            ->withEntityPrefix('App\\Entity\\')
            ->build();

        $this->assertEquals('App\\Entity\\', $config->getEntityPrefix());
    }

    public function testWithEntityPrefixNullSetsNull(): void
    {
        $config = ConfigBuilder::create()
            ->withEntityPrefix(null)
            ->build();

        $this->assertNull($config->getEntityPrefix());
    }

    public function testSortFieldsEnablesSortFields(): void
    {
        $config = ConfigBuilder::create()
            ->sortFields()
            ->build();

        $this->assertTrue($config->getSortFields());
    }

    public function testSortFieldsWithFalseDisablesSortFields(): void
    {
        $config = ConfigBuilder::create()
            ->sortFields(false)
            ->build();

        $this->assertFalse($config->getSortFields());
    }

    public function testExcludeFiltersSetsFilters(): void
    {
        $filters = [Filters::CONTAINS, Filters::BETWEEN];
        $config  = ConfigBuilder::create()
            ->excludeFilters($filters)
            ->build();

        $this->assertEquals($filters, $config->getExcludeFilters());
    }

    public function testExcludeFilterAddsFilter(): void
    {
        $config = ConfigBuilder::create()
            ->excludeFilter(Filters::CONTAINS)
            ->excludeFilter(Filters::BETWEEN)
            ->build();

        $this->assertEquals([Filters::CONTAINS, Filters::BETWEEN], $config->getExcludeFilters());
    }

    public function testFluentInterfaceChaining(): void
    {
        $config = ConfigBuilder::create()
            ->withGroup('api')
            ->withGroupSuffix('API')
            ->useHydratorCache()
            ->useQueryResultCache()
            ->withLimit(100)
            ->globalEnable()
            ->ignoreField('id')
            ->ignoreField('password')
            ->extractByValue()
            ->withEntityPrefix('App\\')
            ->sortFields()
            ->excludeFilter(Filters::CONTAINS)
            ->build();

        $this->assertEquals('api', $config->getGroup());
        $this->assertEquals('API', $config->getGroupSuffix());
        $this->assertTrue($config->getUseHydratorCache());
        $this->assertTrue($config->getUseQueryResultCache());
        $this->assertEquals(100, $config->getLimit());
        $this->assertTrue($config->getGlobalEnable());
        $this->assertEquals(['id', 'password'], $config->getIgnoreFields());
        $this->assertTrue($config->getGlobalByValue());
        $this->assertEquals('App\\', $config->getEntityPrefix());
        $this->assertTrue($config->getSortFields());
        $this->assertEquals([Filters::CONTAINS], $config->getExcludeFilters());
    }

    public function testDefaultValues(): void
    {
        $config = ConfigBuilder::create()->build();

        // Test that defaults match Config class defaults
        $this->assertEquals('default', $config->getGroup());
        $this->assertNull($config->getGroupSuffix());
        $this->assertFalse($config->getUseHydratorCache());
        $this->assertFalse($config->getUseQueryResultCache());
        $this->assertEquals(1000, $config->getLimit());
        $this->assertFalse($config->getGlobalEnable());
        $this->assertEquals([], $config->getIgnoreFields());
        $this->assertNull($config->getGlobalByValue());
        $this->assertNull($config->getEntityPrefix());
        $this->assertNull($config->getSortFields());
        $this->assertEquals([], $config->getExcludeFilters());
    }

    public function testMultipleIgnoreFieldsCombinesProperly(): void
    {
        $config = ConfigBuilder::create()
            ->ignoreFields(['field1', 'field2'])
            ->ignoreField('field3')
            ->ignoreField('field4')
            ->build();

        // The ignoreFields() method overwrites, so only the last call applies
        // Then ignoreField() appends
        $this->assertEquals(['field1', 'field2', 'field3', 'field4'], $config->getIgnoreFields());
    }

    public function testBuilderReturnsNewConfigInstanceEachTime(): void
    {
        $builder = ConfigBuilder::create();

        $config1 = $builder->withGroup('group1')->build();
        $config2 = $builder->withGroup('group2')->build();

        // Each build() should create a new Config instance
        $this->assertNotSame($config1, $config2);
        $this->assertEquals('group2', $config2->getGroup());
    }
}
