<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\GlobalEnable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ArrayObject;
use Doctrine\ORM\EntityManager;
use League\Event\EventDispatcher;
use ReflectionClass;

/**
 * This trait is used to remove complexity from the Driver class.
 * It doesn't change what the Driver does.  It just separates the container work
 * from the Driver.
 */
trait Services
{
    /** @param mixed[] $metadataArray */
    public function __construct(
        readonly EntityManager $entityManager,
        readonly Config|null $config = null,
        readonly array $metadataArray = [],
    ) {
        $metadata = new ArrayObject($metadataArray);

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
                static function (Container $container): Type\Entity\EntityTypeContainer {
                    return (new ReflectionClass(Type\Entity\EntityTypeContainer::class))
                        ->newLazyGhost(static function (Type\Entity\EntityTypeContainer $object) use ($container): void {
                            $object->__construct($container);
                        });
                },
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
                static function (Container $container): Metadata\GlobalEnable {
                    return (new ReflectionClass(Metadata\GlobalEnable::class))
                        ->newLazyGhost(static function (Metadata\GlobalEnable $object) use ($container): void {
                            $object->__construct(
                                $container->get(EntityManager::class),
                                $container->get(Config::class),
                                $container->get(EventDispatcher::class),
                            );
                        });
                },
            )
            ->set(
                Resolve\FieldResolver::class,
                static function (Container $container): Resolve\FieldResolver {
                    return (new ReflectionClass(Resolve\FieldResolver::class))
                        ->newLazyGhost(static function (Resolve\FieldResolver $object) use ($container): void {
                            $object->__construct(
                                $container->get(Config::class),
                                $container->get(Type\Entity\EntityTypeContainer::class),
                            );
                        });
                },
            )
            ->set(
                Resolve\ResolveCollectionFactory::class,
                static function (Container $container): Resolve\ResolveCollectionFactory {
                    return (new ReflectionClass(Resolve\ResolveCollectionFactory::class))
                        ->newLazyGhost(static function (Resolve\ResolveCollectionFactory $object) use ($container): void {
                            $object->__construct(
                                $container->get(EntityManager::class),
                                $container->get(Config::class),
                                $container->get(Resolve\FieldResolver::class),
                                $container->get(Type\TypeContainer::class),
                                $container->get(EntityTypeContainer::class),
                                $container->get(EventDispatcher::class),
                                $container->get('metadata'),
                            );
                        });
                },
            )
            ->set(
                Resolve\ResolveEntityFactory::class,
                static function (Container $container): Resolve\ResolveEntityFactory {
                    return (new ReflectionClass(Resolve\ResolveEntityFactory::class))
                        ->newLazyGhost(static function (Resolve\ResolveEntityFactory $object) use ($container): void {
                            $object->__construct(
                                $container->get(Config::class),
                                $container->get(EntityManager::class),
                                $container->get(EventDispatcher::class),
                                $container->get('metadata'),
                            );
                        });
                },
            )
            ->set(
                Filter\FilterFactory::class,
                static function (Container $container): Filter\FilterFactory {
                    return (new ReflectionClass(Filter\FilterFactory::class))
                        ->newLazyGhost(static function (Filter\FilterFactory $object) use ($container): void {
                            $object->__construct(
                                $container->get(Config::class),
                                $container->get(EntityManager::class),
                                $container->get(Type\TypeContainer::class),
                                $container->get(EventDispatcher::class),
                            );
                        });
                },
            )
            ->set(
                Hydrator\HydratorContainer::class,
                static function (Container $container): Hydrator\HydratorContainer {
                    return (new ReflectionClass(Hydrator\HydratorContainer::class))
                        ->newLazyGhost(static function (Hydrator\HydratorContainer $object) use ($container): void {
                            $object->__construct(
                                $container->get(EntityManager::class),
                                $container->get(Type\Entity\EntityTypeContainer::class),
                            );
                        });
                },
            )
            ->set(
                Input\InputFactory::class,
                static function (Container $container): Input\InputFactory {
                    return (new ReflectionClass(Input\InputFactory::class))
                        ->newLazyGhost(static function (Input\InputFactory $object) use ($container): void {
                            $object->__construct(
                                $container->get(Config::class),
                                $container->get(EntityManager::class),
                                $container->get(Type\Entity\EntityTypeContainer::class),
                                $container->get(Type\TypeContainer::class),
                            );
                        });
                },
            );
    }

    abstract public function set(string $id, mixed $value): mixed;
}
