<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use Doctrine\Persistence\Proxy;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Laminas\Hydrator\HydratorInterface;
use WeakMap;

use function array_key_exists;
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
        protected readonly ToOneLoader $toOneLoader,
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

            // Computed fields are not extracted here; each is computed when queried
            $hydrator                     = $this->getHydrator($source);
            $values                       = $hydrator instanceof DoctrineObjectWithComputed
                ? $hydrator->extractFields($source)
                : $hydrator->extract($source);
            $this->extractValues[$source] = $values;
        }

        if (! array_key_exists($info->fieldName, $values)) {
            $hydrator = $this->getHydrator($source);

            if ($hydrator instanceof DoctrineObjectWithComputed && $hydrator->hasComputedField($info->fieldName)) {
                /** @psalm-suppress MixedAssignment */
                $values[$info->fieldName]     = $hydrator->extractComputedField($source, $info->fieldName);
                $this->extractValues[$source] = $values;
            }
        }

        // A field's value may be of any type
        /** @psalm-suppress MixedAssignment */
        $value = $values[$info->fieldName] ?? null;

        // An unloaded to-one association is loaded in a batch with the others
        if ($this->config->getBatchAssociations() && is_object($value)) {
            return $this->toOneLoader->defer($value);
        }

        return $value;
    }

    private function getHydrator(object $source): HydratorInterface
    {
        $entity = $this->entityTypeContainer->get((new DefaultProxyClassNameResolver())->getClass($source));

        return $entity->getHydrator();
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
