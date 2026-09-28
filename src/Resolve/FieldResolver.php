<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\Persistence\Proxy;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use WeakMap;

use function assert;
use function is_object;

/**
 * A field resolver that uses the Doctrine Laminas hydrator to extract values
 */
final class FieldResolver
{
    /**
     * Hydrator extract results keyed by the entity they were extracted from.
     * A WeakMap releases an entry when its entity is freed, so the values
     * never outlive the entity or are served to another object.
     *
     * @var WeakMap<object, array<array-key, mixed>>
     */
    private WeakMap $extractValues;

    public function __construct(
        protected readonly Config $config,
        protected readonly EntityTypeContainer $entityTypeContainer,
    ) {
        $this->extractValues = self::newExtractCache();
    }

    /** @throws Error */
    public function __invoke(mixed $source, mixed $args, mixed $context, ResolveInfo $info): mixed
    {
        assert(is_object($source), 'A non-object was passed to the FieldResolver.  '
            . 'Verify you\'re wrapping your Doctrine GraphQL type() call in a connection.');

        // Proxy objects cannot hydrate by reference without loading
        if ($source instanceof Proxy) {
            // @codeCoverageIgnoreStart
            $source->__load();
            // @codeCoverageIgnoreEnd
        }

        // A hit is decided by the entity, not the field, so a null field is
        // served from the extract rather than extracting again
        $values = $this->extractValues[$source] ?? null;

        if ($values === null) {
            /**
             * For disabled hydrator cache, keep only the last extract so it is
             * reused for the consecutive fields of one entity
             */
            if (! $this->config->getUseHydratorCache()) {
                $this->extractValues = self::newExtractCache();
            }

            $entity = $this->entityTypeContainer->get((new DefaultProxyClassNameResolver())->getClass($source));
            assert($entity instanceof Entity);
            $values                       = $entity->getHydrator()->extract($source);
            $this->extractValues[$source] = $values;
        }

        return $values[$info->fieldName] ?? null;
    }

    /** @return WeakMap<object, array<array-key, mixed>> */
    private static function newExtractCache(): WeakMap
    {
        // The template types of an empty WeakMap cannot be inferred
        /** @var WeakMap<object, array<array-key, mixed>> $cache */
        $cache = new WeakMap();

        return $cache;
    }
}
