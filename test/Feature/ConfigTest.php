<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use BadMethodCallException;
use InvalidArgumentException;

class ConfigTest extends AbstractTest
{
    public function testConfig(): void
    {
        $config = new Config([
            'group' => 'default',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $this->assertEquals('default', $config->getGroup());
        $this->assertTrue($config->getUseHydratorCache());
        $this->assertEquals(1000, $config->getLimit());
    }

    public function testMagicGetterFailsOnInvalidConfig(): void
    {
        $this->expectException(BadMethodCallException::class);

        $config = new Config([
            'group' => 'default',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);

        $config->getInvalid();
    }

    public function testInvalidConfigProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new Config([
            'invalid' => 'default',
            'useHydratorCache' => true,
            'limit' => 1000,
        ]);
    }
}
