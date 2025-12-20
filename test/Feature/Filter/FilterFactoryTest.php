<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

class FilterFactoryTest extends TestCase
{
    public function testExcludeFilters(): void
    {
        $config = new Config(['group' => 'ExcludeFiltersTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $filter = $driver->filter(Artist::class);

        $this->assertSame($filter, $driver->filter(Artist::class));
    }
}
