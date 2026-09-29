<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\FilterFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Pagination\PaginationService;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\FieldResolver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Resolve\ResolveCollectionFactory;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use Doctrine\ORM\EntityManager;
use League\Event\EventDispatcher;
use Override;
use ReflectionClass;

use function array_keys;
use function array_map;
use function assert;
use function is_array;
use function strtolower;

/**
 * This class is used to manage the Entity classes
 * It does not manage GraphQL types
 */
final class EntityTypeContainer extends Container
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
        return isset($this->container->service(Metadata::class)[$id]);
    }

    /**
     * Create and return an Entity object
     */
    #[Override]
    public function get(string $id, string|null $eventName = null): Entity
    {
        // Allow for entities with a custom eventName
        $key = strtolower($id . ($eventName !== null ? '.' . $eventName : ''));

        if (isset($this->register[$key])) {
            $entity = $this->register[$key];
            assert($entity instanceof Entity);

            return $entity;
        }

        if (! $this->has($id)) {
            throw new MetadataException(
                'Entity ' . $id . ' is not mapped in the GraphQL metadata. ' .
                'Add the #[Entity] attribute to expose this entity.',
            );
        }

        $container = $this->container;

        // Use a Lazy Ghost object
        $this->set(
            $key,
            (new ReflectionClass(Entity::class))
                ->newLazyGhost(static function (Entity $object) use ($container, $id, $eventName): void {
                    $entityMetadata = $container->service(Metadata::class)[$id];
                    assert(is_array($entityMetadata));

                    /** @psalm-suppress DirectConstructorCall */
                    $object->__construct(
                        $eventName,
                        $container->service(Config::class),
                        $container->service(EntityManager::class),
                        $container->service(EntityTypeContainer::class),
                        $container->service(EventDispatcher::class),
                        $container->service(FieldResolver::class),
                        $container->service(FilterFactory::class),
                        $container->service(HydratorContainer::class),
                        $container->service(PaginationService::class),
                        $container->service(ResolveCollectionFactory::class),
                        $container->service(TypeContainer::class),
                        $entityMetadata,
                    );
                }),
        );

        return $this->get($id, $eventName);
    }

    /**
     * Get all registered entity type IDs from metadata
     *
     * @return string[]
     */
    #[Override]
    public function getRegisteredTypes(): array
    {
        return array_map('strval', array_keys($this->container->service(Metadata::class)->getArrayCopy()));
    }
}
