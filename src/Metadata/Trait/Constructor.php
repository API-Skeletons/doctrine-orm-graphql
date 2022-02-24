<?php

namespace ApiSkeletons\Doctrine\GraphQL\Metadata\Trait;

use ApiSkeletons\Doctrine\GraphQL\Driver;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

trait Constructor
{
    protected Driver $driver;
    protected array $metadata;

    public function __construct(Driver $driver, array $metadata)
    {
        $this->driver = $driver;
        $this->metadata = $metadata;
    }
}
