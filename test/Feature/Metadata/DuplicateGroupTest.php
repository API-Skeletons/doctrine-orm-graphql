<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use AssertionError;

class DuplicateGroupTest extends TestCase
{
    public function testDuplicateEntityAttributeForGroup(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroup']));

        $driver->get(Metadata::class);
    }

    public function testDuplicateEntityAttributeForField(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroupField']));

        $driver->get(Metadata::class);
    }

    public function testDuplicateEntityAttributeForAssociation(): void
    {
        $this->expectException(AssertionError::class);

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'DuplicateGroupAssociation']));

        $driver->get(Metadata::class);
    }
}
