<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

/**
 * Types that should be built must implement this interface.  A type is
 * registered by the name it is built with, so it should be named by it, or
 * two types could be registered by the same name.
 */
interface Buildable
{
    /** @param mixed[] $params */
    public function __construct(Container $container, string $typeName, mixed $params);
}
