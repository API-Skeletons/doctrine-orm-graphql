<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\FieldResolver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use League\Event\EventDispatcher;
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
                        $eventName,
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(EntityTypeContainer::class),
                        $container->get(EventDispatcher::class),
                        $container->get(FieldResolver::class),
                        $container->get(FilterFactory::class),
                        $container->get(HydratorContainer::class),
                        $container->get(ResolveCollectionFactory::class),
                        $container->get(TypeContainer::class),
                        $container->get(Metadata::class)[$id],
                    );
                }),
        );

        return $this->get($id, $eventName);
    }
}
