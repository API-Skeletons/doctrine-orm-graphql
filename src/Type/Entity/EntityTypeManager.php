<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

use function assert;
use function strtolower;

/**
 * This class is used to manage the Entity classes
 * It does not manage GraphQL types
 */
class EntityTypeManager extends AbstractContainer
{
    public function __construct(
        protected AbstractContainer $container,
    ) {
        assert($container instanceof Driver);
    }

    /**
     * Use the metadata to determine if the entity is available
     */
    public function has(string $id): bool
    {
        return isset($this->container->get('metadata')[$id]);
    }

    /**
     * Create and return an Entity object
     */
    public function get(string $id): mixed
    {
        $key = strtolower($id);

        if (isset($this->register[$key])) {
            return $this->register[$key];
        }

        $this->set($key, new Entity($this->container, $id));

        return $this->register[$key];
    }
}
