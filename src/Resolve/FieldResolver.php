<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;

use function assert;
use function is_object;
use function spl_object_hash;

/**
 * A field resolver that uses the Doctrine Laminas hydrator to extract values
 */
class FieldResolver
{
    /**
     * Cache all hydrator extract operations based on spl object hash
     *
     * @var mixed[]
     */
    private array $extractValues = [];

    public function __construct(
        protected readonly Config $config,
        protected readonly EntityTypeContainer $entityTypeContainer,
    ) {
    }

    /** @throws Error */
    public function __invoke(mixed $source, mixed $args, mixed $context, ResolveInfo $info): mixed
    {
        assert(is_object($source), 'A non-object was passed to the FieldResolver.  '
            . 'Verify you\'re wrapping your Doctrine GraphQL type() call in a connection.');

        $defaultProxyClassNameResolver = new DefaultProxyClassNameResolver();

        $entityClass   = $defaultProxyClassNameResolver->getClass($source);
        $splObjectHash = spl_object_hash($source);

        /**
         * For disabled hydrator cache, store only the last hydrator result and reuse for consecutive calls
         * then drop the cache if it doesn't hit.
         */
        if (! $this->config->getUseHydratorCache()) {
            if (isset($this->extractValues[$splObjectHash])) {
                return $this->extractValues[$splObjectHash][$info->fieldName] ?? null;
            }

            $this->extractValues = [];

            $this->extractValues[$splObjectHash] = $this->entityTypeContainer
                ->get($entityClass)
                    ->getHydrator()->extract($source);

            return $this->extractValues[$splObjectHash][$info->fieldName] ?? null;
        }

        // Use full hydrator cache
        if (isset($this->extractValues[$splObjectHash][$info->fieldName])) {
            return $this->extractValues[$splObjectHash][$info->fieldName] ?? null;
        }

        $this->extractValues[$splObjectHash] = $this->entityTypeContainer
            ->get($entityClass)
            ->getHydrator()->extract($source);

        return $this->extractValues[$splObjectHash][$info->fieldName] ?? null;
    }
}
