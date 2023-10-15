<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\NamingStrategy;

use Laminas\Hydrator\NamingStrategy\NamingStrategyInterface;

class CustomNamingStrategy implements NamingStrategyInterface
{
    public function extract(string $name, object|null $object = null): string
    {
        return $name;
    }

    public function hydrate(string $name, array|null $data = null): string
    {
        return $name;
    }
}
