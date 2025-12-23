<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use GraphQL\Error\Error;
use Override;
use ReflectionClass;

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
    }

    /**
     * Use the metadata to determine if the entity is available
     */
    #[Override]
    public function has(string $id): bool
    {
        return isset($this->container->get(Metadata::class)[$id]);
    }

    /**
     * Create and return an Entity object
     */
    #[Override]
    public function get(string $id, string|null $eventName = null): mixed
    {
        // Allow for entities with a custom eventName
        $key = strtolower($id . ($eventName ? '.' . $eventName : ''));

        if (isset($this->register[$key])) {
            return $this->register[$key];
        }

        if (! $this->has($id)) {
            throw new Error(
                'Entity ' . $id . ' is not mapped in the GraphQL metadata',
            );
        }

        $container = $this->container;

        // Use a Lazy Ghost object
        $this->set(
            $key,
            (new ReflectionClass(Entity::class))
                ->newLazyGhost(static function (Entity $object) use ($container, $id, $eventName): void {
                    $object->__construct(
                        $container,
                        $id,
                        $eventName,
                    );
                }),
        );

        return $this->get($id, $eventName);
    }
}
