<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

use function array_keys;
use function array_merge;
use function property_exists;

/**
 * This class is used for setting parameters when
 * creating the driver
 */
final class Config
{
    /**
     * @var string The GraphQL group. This allows multiple GraphQL
     *             configurations within the same application or
     *             even within the same group of entities and Object Manager.
     */
    protected readonly string $group;

    /**
     * @var string|null The group is usually suffixed to GraphQL type names.
     *                  You may specify a different string for the group suffix
     *                  or you my supply an empty string to exclude the suffix.
     *                  Be warned, using the same groupSuffix with two different
     *                  groups can cause collisions.
     */
    protected readonly string|null $groupSuffix;

    /**
     * @var bool When set to true hydrator results will be cached for as
     *           long as the entity they were extracted from exists, thereby
     *           saving multiple extracts for the same entity.
     */
    protected readonly bool $useHydratorCache;

    /**
     * @var bool When set to true query results will be cached for the
     *           duration of the request thereby preventing duplicate database
     *           queries with identical SQL and parameters.
     */
    protected readonly bool $useQueryResultCache;

    /**
     * @var bool When set to true, associations are loaded in batches: all the
     *           unloaded to-one associations and all the collections of a
     *           field are loaded together rather than once per row.
     */
    protected readonly bool $batchAssociations;

    /**
     * @var int When the rows of all the sources in a batched collection field
     *          number no more than this, they are fetched with one query and
     *          paginated in PHP.  Otherwise each source's page is queried.
     */
    protected readonly int $batchLimit;

    /** @var int A hard limit for fetching any collection within the schema */
    protected readonly int $limit;

    /**
     * @var bool|null When set to true, all entities will be extracted by value
     *                across all hydrators in the driver.  When set to false,
     *                all hydrators will extract by reference.  This overrides
     *                per-entity attribute configuration.
     */
    protected readonly bool|null $extractByValue;

    /**
     * @var string|null When set, the entityPrefix will be removed from each
     *                  type name.  This simplifies type names and makes reading
     *                  the GraphQL documentation easier.
     */
    protected readonly string|null $entityPrefix;

    /**
     * @var bool|null When set to true entity fields will be
     *                sorted alphabetically
     */
    protected readonly bool|null $sortFields;

    /**
     * @var Filters[] An array of filters to exclude from
     *                available filters for all fields and
     *                associations in every entity
     */
    protected readonly array $excludeFilters;

    /** @param mixed[] $config */
    public function __construct(array $config = [])
    {
        $default = [
            'group' => 'default',
            'groupSuffix' => null,
            'useHydratorCache' => false,
            'useQueryResultCache' => false,
            'batchAssociations' => true,
            'batchLimit' => 1000,
            'limit' => 1000,
            'extractByValue' => null,
            'entityPrefix' => null,
            'sortFields' => null,
            'excludeFilters' => [],
        ];

        /** @var array{group: string, groupSuffix: string|null, useHydratorCache: bool, useQueryResultCache: bool, batchAssociations: bool, batchLimit: int, limit: int, extractByValue: bool|null, entityPrefix: string|null, sortFields: bool|null, excludeFilters: Filters[]} $mergedConfig */
        $mergedConfig = array_merge($default, $config);

        foreach ($mergedConfig as $field => $value) {
            if (! property_exists($this, $field)) {
                throw new ConfigurationException(
                    'Invalid configuration setting: ' . $field,
                    array_keys($default),
                );
            }
        }

        // Assigning properties explicitly is phpstan friendly
        $this->group               = $mergedConfig['group'];
        $this->groupSuffix         = $mergedConfig['groupSuffix'];
        $this->useHydratorCache    = $mergedConfig['useHydratorCache'];
        $this->useQueryResultCache = $mergedConfig['useQueryResultCache'];
        $this->batchAssociations   = $mergedConfig['batchAssociations'];
        $this->batchLimit          = $mergedConfig['batchLimit'];
        $this->limit               = $mergedConfig['limit'];
        $this->extractByValue      = $mergedConfig['extractByValue'];
        $this->entityPrefix        = $mergedConfig['entityPrefix'];
        $this->sortFields          = $mergedConfig['sortFields'];
        $this->excludeFilters      = $mergedConfig['excludeFilters'];
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getGroupSuffix(): string|null
    {
        return $this->groupSuffix;
    }

    public function getUseHydratorCache(): bool
    {
        return $this->useHydratorCache;
    }

    public function getUseQueryResultCache(): bool
    {
        return $this->useQueryResultCache;
    }

    public function getBatchAssociations(): bool
    {
        return $this->batchAssociations;
    }

    public function getBatchLimit(): int
    {
        return $this->batchLimit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getExtractByValue(): bool|null
    {
        return $this->extractByValue;
    }

    public function getEntityPrefix(): string|null
    {
        return $this->entityPrefix;
    }

    public function getSortFields(): bool|null
    {
        return $this->sortFields;
    }

    /** @return Filters[] */
    public function getExcludeFilters(): array
    {
        return $this->excludeFilters;
    }
}
