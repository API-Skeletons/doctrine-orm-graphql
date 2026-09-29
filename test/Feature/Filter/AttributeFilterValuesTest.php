<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Association;
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Field;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * An attribute's filters may be Filters cases or their values, as Config's
 */
class AttributeFilterValuesTest extends TestCase
{
    public function testExcludeFiltersMayBeValues(): void
    {
        $this->assertSame([Filters::EQ, Filters::NEQ], (new Field(excludeFilters: ['eq', Filters::NEQ]))->getExcludeFilters());
        $this->assertSame([Filters::CONTAINS], (new Entity(excludeFilters: ['contains']))->getExcludeFilters());
        $this->assertSame([Filters::ISNULL], (new Association(excludeFilters: ['isnull']))->getExcludeFilters());
    }

    public function testIncludeFiltersMayBeValues(): void
    {
        $excluded = (new Field(includeFilters: ['eq', Filters::NEQ]))->getExcludeFilters();

        $this->assertNotContains(Filters::EQ, $excluded);
        $this->assertNotContains(Filters::NEQ, $excluded);
        $this->assertCount(count(Filters::cases()) - 2, $excluded);
    }

    public function testUnknownFilterIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid excludeFilters filter: "equals" is not a filter.');

        (new Field(excludeFilters: ['equals']))->getExcludeFilters();
    }

    public function testFilterOfAnotherTypeIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid includeFilters filter: int is not a filter.');

        (new Field(includeFilters: [1]))->getExcludeFilters();
    }
}
