<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\GlobalEnable;
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
            ->set(
                EventDispatcher::class,
                static fn () => new EventDispatcher()
            )
            ->set(
                Type\TypeManager::class,
                static fn (AbstractContainer $container) => new Type\TypeManager($container)
            )
            ->set(
                'metadata',
                static function (AbstractContainer $container) use ($metadata) {
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
                static function (AbstractContainer $container) {
                    return new Metadata\GlobalEnable(
                        $container->get(EntityManager::class),
                        $container->get(Config::class),
                        $container->get(EventDispatcher::class),
                    );
                },
            )
            ->set(
                Resolve\FieldResolver::class,
                static function (AbstractContainer $container) {
                    return new Resolve\FieldResolver(
                        $container->get(Config::class),
                        $container->get(Type\TypeManager::class),
                    );
                },
            )
            ->set(
                Resolve\ResolveCollectionFactory::class,
                static function (AbstractContainer $container) {
                    return new Resolve\ResolveCollectionFactory(
                        $container->get(EntityManager::class),
                        $container->get(Config::class),
                        $container->get(Resolve\FieldResolver::class),
                        $container->get(Type\TypeManager::class),
                        $container->get(EventDispatcher::class),
                        $container->get('metadata'),
                    );
                },
            )
            ->set(
                Resolve\ResolveEntityFactory::class,
                static function (AbstractContainer $container) {
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
                static function (AbstractContainer $container) {
                    return new Filter\FilterFactory(
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(Type\TypeManager::class),
                        $container->get(EventDispatcher::class),
                    );
                },
            )
            ->set(
                Hydrator\HydratorFactory::class,
                static function (AbstractContainer $container) {
                    return new Hydrator\HydratorFactory(
                        $container->get(EntityManager::class),
                        $container->get(Type\TypeManager::class),
                    );
                },
            )
            ->set(
                Input\InputFactory::class,
                static function (AbstractContainer $container) {
                    return new Input\InputFactory(
                        $container->get(Config::class),
                        $container->get(EntityManager::class),
                        $container->get(Type\TypeManager::class),
                    );
                },
            );
    }

    abstract public function set(string $id, mixed $value): mixed;
}
