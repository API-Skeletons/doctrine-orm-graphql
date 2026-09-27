<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Connection;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use stdClass;

use function ini_get;
use function ini_set;

class TypeContainerTest extends TestCase
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

    /**
     * Only a Buildable class can be built, whether or not assertions are
     * enabled
     */
    public function testBuildRejectsAClassWhichIsNotBuildable(): void
    {
        $previous = ini_get('zend.assertions');
        // zend.assertions=-1 cannot be changed at runtime; assertions are already off
        if ($previous !== '-1') {
            ini_set('zend.assertions', '0');
        }

        try {
            $typeContainer = new TypeContainer();

            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage('stdClass cannot be built because it does not implement');

            $typeContainer->build(stdClass::class, 'notBuildable');
        } finally {
            if ($previous !== '-1') {
                ini_set('zend.assertions', (string) $previous);
            }
        }
    }
}
