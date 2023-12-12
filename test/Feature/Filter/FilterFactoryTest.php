<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;

class FilterFactoryTest extends AbstractTest
{
    public function testExcludeFilters(): void
    {
        $config = new Config(['group' => 'ExcludeFiltersTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $filter = $driver->filter(Artist::class);

        $this->assertSame($filter, $driver->filter(Artist::class));
    }
}
