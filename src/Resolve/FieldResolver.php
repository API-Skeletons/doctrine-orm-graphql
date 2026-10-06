<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\Persistence\Proxy;
use GraphQL\Deferred;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Laminas\Hydrator\HydratorInterface;
use WeakMap;

use function array_key_exists;
use function assert;
use function is_array;
use function is_iterable;
use function is_object;
use function serialize;

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
        protected readonly ComputedFieldBatchLoader $computedFieldBatchLoader,
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

        $key = $info->fieldName;

        if (! array_key_exists($key, $values)) {
            $hydrator = $this->getHydrator($source);

            if ($hydrator instanceof DoctrineObjectWithComputed && $hydrator->hasComputedField($info->fieldName)) {
                // A computed field with arguments has a value for each set of
                // them, as aliases of the field may give different arguments
                $args = is_array($args) ? $args : [];
                if ($hydrator->hasComputedFieldArguments($info->fieldName)) {
                    $key .= '(' . serialize($args) . ')';
                }

                if (! array_key_exists($key, $values)) {
                    // A batched field is loaded with the other entities waiting for it
                    $batchExtractor = $this->config->getBatchAssociations()
                        ? $hydrator->getBatchExtractor($info->fieldName)
                        : null;

                    /** @psalm-suppress MixedAssignment */
                    $values[$key]                 = $batchExtractor !== null
                        ? $this->computedFieldBatchLoader->defer(
                            $source,
                            $this->entityTypeContainer->getExposedClass($source),
                            $info->fieldName,
                            $batchExtractor,
                            $args,
                        )
                        : $hydrator->extractComputedField($source, $info->fieldName, $args);
                    $this->extractValues[$source] = $values;
                }
            }
        }

        // A field's value may be of any type
        /** @psalm-suppress MixedAssignment */
        $value = $values[$key] ?? null;

        if (! $this->config->getBatchAssociations()) {
            return $value;
        }

        // The value of a batched computed field, once loaded
        if ($value instanceof Deferred) {
            return $value->then(fn (mixed $loaded): mixed => $this->deferEntities($loaded, $info));
        }

        return $this->deferEntities($value, $info);
    }

    /**
     * The entities of a value, loaded in a batch with the others
     */
    private function deferEntities(mixed $value, ResolveInfo $info): mixed
    {
        // The entities of a list, such as a computed field may return, are
        // loaded in a batch with the others
        if (is_iterable($value) && Type::getNullableType($info->returnType) instanceof ListOfType) {
            $list = [];

            /** @psalm-suppress MixedAssignment A list may hold any value */
            foreach ($value as $item) {
                $list[] = is_object($item) ? $this->toOneLoader->defer($item) : $item;
            }

            return $list;
        }

        // An unloaded entity, of a to-one association or a computed field, is
        // loaded in a batch with the others
        if (is_object($value)) {
            return $this->toOneLoader->defer($value);
        }

        return $value;
    }

    private function getHydrator(object $source): HydratorInterface
    {
        // A subclass which is not exposed is extracted as its exposed parent
        $entity = $this->entityTypeContainer->get($this->entityTypeContainer->getExposedClass($source));

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
