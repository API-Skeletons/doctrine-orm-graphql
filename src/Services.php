<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\GlobalEnable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use League\Event\EventDispatcher;

/**
 * This trait is used to remove complexity from the Driver class.
 * It doesn't change what the Driver does.  It just separates the container work
 * from the Driver.
 */
trait Services
{
    /** @param mixed[] $metadata */
    public function __construct(
        EntityManager $entityManager,
        Config|null $config = null,
        array $metadata = [],
    ) {
        $metadata = new ArrayObject($metadata);

        $this
            ->set(EntityManager::class, $entityManager)
            ->set(
                Config::class,
                static function () use ($config) {
                    if (! $config) {
                        $config = new Config();
                    }

                    return $config;
                },
            )
            ->set(EventDispatcher::class, static fn () => new EventDispatcher())
            ->set(Type\TypeContainer::class, static fn () => new Type\TypeContainer())
            ->set(
                Type\Entity\EntityTypeContainer::class,
                static fn (Container $container) => new Type\Entity\EntityTypeContainer($container),
            )
            ->set(
                'metadata',
                static function (Container $container) use ($metadata) {
                    return (new Metadata\MetadataFactory(
                        $metadata,
                        $container->get(EntityManager::class),
                        $container->get(Config::class),
                        $container->get(GlobalEnable::class),
                        $container->get(EventDispatcher::class),
                    ))();
                },
            )
            ->set(
                Metadata\GlobalEnable::class,
                static function (Container $container) {
                    return new Metadata\GlobalEnable(
                        $container->get(EntityManager::class),
                        $container->get(Config::class),
                        $container->get(EventDispatcher::class),
                    );
                },
            )
            ->set(
                Resolve\FieldResolver::class,
                static function (Container $container) {
                    return new Resolve\FieldResolver(
                        $container->get(Config::class),
                        $container->get(Type\Entity\EntityTypeContainer::class),
                    );
                },
            )
            ->set(
                Resolve\ResolveCollectionFactory::class,
                static function (Container $container) {
                    return new Resolve\ResolveCollectionFactory(
                        $container->get(EntityManager::class),
                        $container->get(Config::class),
                        $container->get(Resolve\FieldResolver::class),
                        $container->get(Type\TypeContainer::class),
                        $container->get(EntityTypeContainer::class),
                        $container->get(EventDispatcher::class),
                        $container->get('metadata'),
                    );
                },
            )
            ->set(
                Resolve\ResolveEntityFactory::class,
                static function (Container $container) {
                    return new Resolve\ResolveEntityFactory(
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(EventDispatcher::class),
                        $container->get('metadata'),
                    );
                },
            )
            ->set(
                Filter\FilterFactory::class,
                static function (Container $container) {
                    return new Filter\FilterFactory(
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(Type\TypeContainer::class),
                        $container->get(EventDispatcher::class),
                    );
                },
            )
            ->set(
                Hydrator\HydratorContainer::class,
                static function (Container $container) {
                    return new Hydrator\HydratorContainer(
                        $container->get(EntityManager::class),
                        $container->get(Type\Entity\EntityTypeContainer::class),
                    );
                },
            )
            ->set(
                Input\InputFactory::class,
                static function (Container $container) {
                    return new Input\InputFactory(
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(Type\Entity\EntityTypeContainer::class),
                        $container->get(Type\TypeContainer::class),
                    );
                },
            );
    }

    abstract public function set(string $id, mixed $value): mixed;
}
