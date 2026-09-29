<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Cache\QueryResultCache;
use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * A service is got by its class name, typed as that class
 */
class ContainerTest extends TestCase
{
    public function testServiceIsTheRegisteredObject(): void
    {
        $config = new Config(['group' => 'ContainerTest']);
        $driver = new Driver($this->getEntityManager(), $config);

        $this->assertSame($config, $driver->service(Config::class));
        $this->assertSame($driver->get(QueryResultCache::class), $driver->service(QueryResultCache::class));
    }

    public function testServiceOfAnotherClassThrows(): void
    {
        $driver = new Driver($this->getEntityManager());
        $driver->set(QueryResultCache::class, new stdClass());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'The service registered as ' . QueryResultCache::class . ' is stdClass, not ' . QueryResultCache::class . '.',
        );

        $driver->service(QueryResultCache::class);
    }

    /**
     * The driver's constructor arguments are services, not public properties;
     * a Config property would be null when the default Config is used
     */
    public function testDriverHasNoPublicProperties(): void
    {
        $properties = (new ReflectionClass(Driver::class))->getProperties(ReflectionProperty::IS_PUBLIC);

        $this->assertSame([], $properties);
        $this->assertInstanceOf(Config::class, (new Driver($this->getEntityManager()))->service(Config::class));
    }
}
