<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
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
    /** @param array<string, mixed> $metadataArray */
    public function __construct(
        readonly EntityManager $entityManager,
        readonly Config|null $config = null,
        readonly array $metadataArray = [],
    ) {
        $self     = $this;
        $metadata = Metadata::fromArray($metadataArray);

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
                Pagination\PaginationService::class,
                static fn () => new Pagination\PaginationService(),
            )
            ->set(
                Cache\QueryResultCache::class,
                static function (Container $container) use ($entityManager): Cache\QueryResultCache {
                    $cache  = new Cache\QueryResultCache();
                    $config = $container->service(Config::class);

                    // The cache lasts until the entity manager is cleared or flushed
                    if ($config->getUseQueryResultCache()) {
                        Cache\QueryResultCacheListener::register($entityManager, $cache);
                    }

                    return $cache;
                },
            )
            ->set(
                Type\Entity\EntityTypeContainer::class,
                (new ReflectionClass(Type\Entity\EntityTypeContainer::class))
                    ->newLazyGhost(static function (Type\Entity\EntityTypeContainer $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct($self);
                    }),
            )
            ->set(
                Metadata::class,
                static function (Container $container) use ($metadata) {
                    $entityManager   = $container->service(EntityManager::class);
                    $config          = $container->service(Config::class);
                    $eventDispatcher = $container->service(EventDispatcher::class);

                    return (new Metadata\MetadataFactory(
                        $metadata,
                        $entityManager,
                        $config,
                        $eventDispatcher,
                    ))->getMetadata();
                },
            )
            ->set(
                Resolve\FieldResolver::class,
                (new ReflectionClass(Resolve\FieldResolver::class))
                    ->newLazyGhost(static function (Resolve\FieldResolver $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(Config::class),
                            $self->service(Type\Entity\EntityTypeContainer::class),
                            $self->service(Resolve\ToOneLoader::class),
                        );
                    }),
            )
            ->set(
                Resolve\ToOneLoader::class,
                static function (Container $container) {
                    $entityManager = $container->service(EntityManager::class);

                    return new Resolve\ToOneLoader($entityManager);
                },
            )
            ->set(
                Resolve\ResolveCollectionFactory::class,
                (new ReflectionClass(Resolve\ResolveCollectionFactory::class))
                    ->newLazyGhost(static function (Resolve\ResolveCollectionFactory $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(EntityManager::class),
                            $self->service(Config::class),
                            $self->service(Resolve\FieldResolver::class),
                            $self->service(Type\TypeContainer::class),
                            $self->service(EntityTypeContainer::class),
                            $self->service(EventDispatcher::class),
                            $self->service(Pagination\PaginationService::class),
                            $self->service(Cache\QueryResultCache::class),
                        );
                    }),
            )
            ->set(
                Resolve\ResolveEntityFactory::class,
                (new ReflectionClass(Resolve\ResolveEntityFactory::class))
                    ->newLazyGhost(static function (Resolve\ResolveEntityFactory $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(Config::class),
                            $self->service(EntityManager::class),
                            $self->service(EventDispatcher::class),
                            $self->service(Pagination\PaginationService::class),
                            $self->service(Cache\QueryResultCache::class),
                        );
                    }),
            )
            ->set(
                Resolve\ResolveDbalFactory::class,
                (new ReflectionClass(Resolve\ResolveDbalFactory::class))
                    ->newLazyGhost(static function (Resolve\ResolveDbalFactory $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(Config::class),
                            $self->service(Pagination\PaginationService::class),
                        );
                    }),
            )
            ->set(
                Filter\FilterFactory::class,
                (new ReflectionClass(Filter\FilterFactory::class))
                    ->newLazyGhost(static function (Filter\FilterFactory $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(Config::class),
                            $self->service(EntityManager::class),
                            $self->service(Type\TypeContainer::class),
                            $self->service(EventDispatcher::class),
                        );
                    }),
            )
            ->set(
                Hydrator\HydratorContainer::class,
                (new ReflectionClass(Hydrator\HydratorContainer::class))
                    ->newLazyGhost(static function (Hydrator\HydratorContainer $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(EntityManager::class),
                            $self->service(Type\Entity\EntityTypeContainer::class),
                        );
                    }),
            )
            ->set(
                Input\InputFactory::class,
                (new ReflectionClass(Input\InputFactory::class))
                    ->newLazyGhost(static function (Input\InputFactory $object) use ($self): void {
                        /** @psalm-suppress DirectConstructorCall */
                        $object->__construct(
                            $self->service(Config::class),
                            $self->service(EntityManager::class),
                            $self->service(Type\Entity\EntityTypeContainer::class),
                            $self->service(Type\TypeContainer::class),
                        );
                    }),
            );
    }

    abstract public function set(string $id, mixed $value): mixed;
}
