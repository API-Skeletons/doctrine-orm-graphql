<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Resolve;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\DefaultProxyClassNameResolver;
use GraphQL\Deferred;

use function array_chunk;
use function array_values;
use function count;
use function is_scalar;
use function serialize;

/**
 * Load unloaded to-one associations in batches
 *
 * A to-one association which is not yet loaded is a Doctrine proxy.  Rather
 * than let each proxy load itself with its own query when its fields are
 * read, its identifier is recorded and a Deferred is returned.  When the
 * executor resolves the Deferred values, every recorded identifier of a class
 * is loaded with one query, which initializes the proxies.
 */
final class ToOneLoader
{
    /**
     * Identifiers to load, keyed by class and then by a key unique to the
     * identifier
     *
     * @var array<class-string, array<string, mixed>>
     */
    private array $pending = [];

    /** @param positive-int $chunkSize The most identifiers loaded by one query */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly int $chunkSize = 1000,
    ) {
    }

    /**
     * Return a Deferred for an unloaded entity, or the value unchanged
     */
    public function defer(object $value): object
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();

        if (! $unitOfWork->isUninitializedObject($value)) {
            return $value;
        }

        $class    = (new DefaultProxyClassNameResolver())->getClass($value);
        $metadata = $this->entityManager->getClassMetadata($class);

        // A composite identifier cannot be matched with IN; it loads itself
        if (count($metadata->getIdentifierFieldNames()) !== 1) {
            return $value;
        }

        $identifier = $unitOfWork->getEntityIdentifier($value);
        // An identifier may be of any type, such as an int, a string or a UUID object
        /** @psalm-suppress MixedAssignment */
        $id = $identifier[$metadata->getSingleIdentifierFieldName()];

        $this->pending[$class][is_scalar($id) ? (string) $id : serialize($id)] = $id;

        return new Deferred(function () use ($class, $value): object {
            $this->load($class);

            return $value;
        });
    }

    /**
     * Load every pending identifier of a class.  Loading the entities
     * initializes their proxies.
     *
     * @param class-string $class
     */
    private function load(string $class): void
    {
        $ids = $this->pending[$class] ?? [];
        unset($this->pending[$class]);

        if ($ids === []) {
            return;
        }

        $idField = $this->entityManager->getClassMetadata($class)->getSingleIdentifierFieldName();

        foreach (array_chunk(array_values($ids), $this->chunkSize) as $chunk) {
            $this->entityManager->createQueryBuilder()
                ->select('entity')
                ->from($class, 'entity')
                ->where('entity.' . $idField . ' IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getResult();
        }
    }
}
