<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Connection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;

class TypeContainerTest extends AbstractTest
{
    public function testBuild(): void
    {
        $driver        = new Driver($this->getEntityManager());
        $typeContainer = $driver->get(TypeContainer::class);

        $objectType = $driver->type(Artist::class);
        $connection = $typeContainer->build(Connection::class, $objectType->name, $objectType);
        $this->assertEquals('Connection_' . $objectType->name, $connection->name);
    }

    public function testBuildTwiceReturnsSameType(): void
    {
        $driver        = new Driver($this->getEntityManager());
        $typeContainer = $driver->get(TypeContainer::class);

        $objectType  = $driver->type(Artist::class);
        $connection1 = $typeContainer->build(Connection::class, $objectType->name, $objectType);
        $connection2 = $typeContainer->build(Connection::class, $objectType->name, $objectType);

        $this->assertSame($connection1, $connection2);
    }
}
