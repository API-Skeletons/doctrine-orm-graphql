<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Connection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;

class TypeManagerTest extends AbstractTest
{
    public function testBuild(): void
    {
        $driver      = new Driver($this->getEntityManager());
        $typeManager = $driver->get(TypeManager::class);

        $objectType = $driver->get(TypeManager::class)->build(Entity::class, Artist::class)();
        $connection = $typeManager->build(Connection::class, $objectType->name . '_Connection', $objectType);
        $this->assertEquals($objectType->name . '_Connection', $connection->name);
    }

    public function testBuildTwiceReturnsSameType(): void
    {
        $driver      = new Driver($this->getEntityManager());
        $typeManager = $driver->get(TypeManager::class);

        $objectType  = $driver->get(TypeManager::class)->build(Entity::class, Artist::class)();
        $connection1 = $typeManager->build(Connection::class, $objectType->name . '_Connection', $objectType);
        $connection2 = $typeManager->build(Connection::class, $objectType->name . '_Connection', $objectType);

        $this->assertSame($connection1, $connection2);
    }
}
