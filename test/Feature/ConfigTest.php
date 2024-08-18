<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use InvalidArgumentException;

class ConfigTest extends AbstractTest
{
    public function testInvalidConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Config(['invalid' => 'invalid']);
    }
}
