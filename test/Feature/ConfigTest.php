<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

class ConfigTest extends TestCase
{
    public function testInvalidConfig(): void
    {
        $this->expectException(ConfigurationException::class);
        new Config(['invalid' => 'invalid']);
    }
}
