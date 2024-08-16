<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits;

use BadMethodCallException;

use function lcfirst;
use function property_exists;
use function sprintf;
use function str_starts_with;
use function substr;

trait MagicGetter
{
    /** @param mixed[] $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        $property = lcfirst(substr($name, 3));

        if (str_starts_with($name, 'get') && property_exists($this, $property)) {
            return $this->$property;
        }

        throw new BadMethodCallException(sprintf('Method %s does not exist', $name));
    }
}
