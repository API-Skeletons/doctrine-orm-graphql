<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Criteria;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;

class CriteriaFactoryTest extends AbstractTest
{
    public function testExcludeCriteria(): void
    {
        $config = new Config(['group' => 'ExcludeCriteriaTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $filter = $driver->filter(Artist::class);

        $this->assertSame($filter, $driver->filter(Artist::class));
    }
}
