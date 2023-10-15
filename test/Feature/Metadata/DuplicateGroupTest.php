<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use AssertionError;

class DuplicateGroupTest extends AbstractTest
{
    public function testDuplicateEntityAttributeForGroup(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroup']));

        $driver->get('metadata');
    }

    public function testDuplicateEntityAttributeForField(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroupField']));

        $driver->get('metadata');
    }

    public function testDuplicateEntityAttributeForAssociation(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroupAssociation']));

        $driver->get('metadata');
    }
}
