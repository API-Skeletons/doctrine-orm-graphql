<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Unit\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveDbalFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The resetOrderBy() of the factory supports the DBAL 3 and the DBAL 4 method
 * of removing an ORDER BY.  Only the method of the installed DBAL is reachable
 * through a real QueryBuilder so the private method is called directly.
 */
class ResolveDbalFactoryTest extends TestCase
{
    private function getResetOrderBy(): ReflectionMethod
    {
        return new ReflectionMethod(ResolveDbalFactory::class, 'resetOrderBy');
    }

    private function getFactory(): ResolveDbalFactory
    {
        return new ResolveDbalFactory(new Config(), new PaginationService());
    }

    public function testResetOrderByUsesResetOrderByWhenAvailable(): void
    {
        $queryBuilder = new class {
            public bool $called = false;

            public function resetOrderBy(): void
            {
                $this->called = true;
            }
        };

        $this->getResetOrderBy()->invoke($this->getFactory(), $queryBuilder);

        $this->assertTrue($queryBuilder->called);
    }

    public function testResetOrderByFallsBackToResetQueryPart(): void
    {
        $queryBuilder = new class {
            public string|null $queryPart = null;

            public function resetQueryPart(string $queryPart): void
            {
                $this->queryPart = $queryPart;
            }
        };

        $this->getResetOrderBy()->invoke($this->getFactory(), $queryBuilder);

        $this->assertEquals('orderBy', $queryBuilder->queryPart);
    }

    public function testResetOrderByIgnoresAQueryBuilderWithNeitherMethod(): void
    {
        $queryBuilder = new class {
        };

        $this->getResetOrderBy()->invoke($this->getFactory(), $queryBuilder);

        $this->assertObjectNotHasProperty('queryPart', $queryBuilder);
    }
}
