<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;

use function assert;
use function strtolower;

/**
 * This class is used to manage the Entity classes
 * It does not manage GraphQL types
 */
class EntityTypeContainer extends Container
{
    public function __construct(
        protected readonly Container $container,
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
    public function get(string $id, string|null $eventName = null): mixed
    {
        // Allow for entities with a custom eventName
        $key = strtolower($id . ($eventName ? '.' . $eventName : ''));

        if (isset($this->register[$key])) {
            return $this->register[$key];
        }

        $this->set($key, new Entity($this->container, $id, $eventName));

        return $this->get($id, $eventName);
    }
}
