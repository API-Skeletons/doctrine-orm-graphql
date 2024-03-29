<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

/**
 * Types that should be built must implement this interface
 */
interface Buildable
{
    /** @param mixed[] $params */
    public function __construct(Container $container, string $typeName, array $params);
}
