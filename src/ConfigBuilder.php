<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;

/**
 * Fluent builder for creating Config instances
 *
 * Example usage:
 * <code>
 * $config = ConfigBuilder::create()
 *     ->withGroup('api')
 *     ->withLimit(100)
 *     ->globalEnable()
 *     ->useHydratorCache()
 *     ->useQueryResultCache()
 *     ->build();
 * </code>
 */
class ConfigBuilder
{
    private string $group             = 'default';
    private string|null $groupSuffix  = null;
    private bool $useHydratorCache    = false;
    private bool $useQueryResultCache = false;
    private int $limit                = 1000;
    private bool $globalEnable        = false;
    /** @var string[] */
    private array $ignoreFields       = [];
    private bool|null $globalByValue  = null;
    private string|null $entityPrefix = null;
    private bool|null $sortFields     = null;
    /** @var Filters[] */
    private array $excludeFilters = [];

    /**
     * Create a new ConfigBuilder instance
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set the GraphQL group name
     *
     * The group allows multiple GraphQL configurations within
     * the same application or even within the same group of
     * entities and Object Manager.
     */
    public function withGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Set the group suffix for GraphQL type names
     *
     * The group is usually suffixed to GraphQL type names.
     * You may specify a different string for the group suffix
     * or you may supply an empty string to exclude the suffix.
     * Be warned, using the same groupSuffix with two different
     * groups can cause collisions.
     */
    public function withGroupSuffix(string|null $groupSuffix): self
    {
        $this->groupSuffix = $groupSuffix;

        return $this;
    }

    /**
     * Enable hydrator caching
     *
     * When set to true hydrator results will be cached for the
     * duration of the request thereby saving multiple extracts
     * for the same entity.
     */
    public function useHydratorCache(bool $enable = true): self
    {
        $this->useHydratorCache = $enable;

        return $this;
    }

    /**
     * Enable query result caching
     *
     * When set to true query results will be cached for the
     * duration of the request thereby preventing duplicate database
     * queries with identical SQL and parameters.
     */
    public function useQueryResultCache(bool $enable = true): self
    {
        $this->useQueryResultCache = $enable;

        return $this;
    }

    /**
     * Set the hard limit for fetching any collection
     *
     * This sets a hard limit for fetching any collection
     * within the schema.
     */
    public function withLimit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Enable all fields and associations globally
     *
     * When set to true all fields and all associations will be
     * enabled. This is best used as a development setting when
     * the entities are subject to change.
     */
    public function globalEnable(bool $enable = true): self
    {
        $this->globalEnable = $enable;

        return $this;
    }

    /**
     * Set field names to ignore when using globalEnable
     *
     * @param string[] $fields
     */
    public function ignoreFields(array $fields): self
    {
        $this->ignoreFields = $fields;

        return $this;
    }

    /**
     * Add a field name to ignore when using globalEnable
     */
    public function ignoreField(string $field): self
    {
        $this->ignoreFields[] = $field;

        return $this;
    }

    /**
     * Set global extraction strategy
     *
     * When set to true, all entities will be extracted by value
     * across all hydrators in the driver. When set to false,
     * all hydrators will extract by reference. This overrides
     * per-entity attribute configuration.
     */
    public function extractByValue(bool $byValue = true): self
    {
        $this->globalByValue = $byValue;

        return $this;
    }

    /**
     * Set global extraction strategy to by reference
     */
    public function extractByReference(): self
    {
        $this->globalByValue = false;

        return $this;
    }

    /**
     * Set entity prefix to remove from type names
     *
     * When set, the entityPrefix will be removed from each
     * type name. This simplifies type names and makes reading
     * the GraphQL documentation easier.
     */
    public function withEntityPrefix(string|null $entityPrefix): self
    {
        $this->entityPrefix = $entityPrefix;

        return $this;
    }

    /**
     * Enable alphabetical sorting of entity fields
     */
    public function sortFields(bool $sort = true): self
    {
        $this->sortFields = $sort;

        return $this;
    }

    /**
     * Exclude specific filters from all fields and associations
     *
     * @param Filters[] $filters
     */
    public function excludeFilters(array $filters): self
    {
        $this->excludeFilters = $filters;

        return $this;
    }

    /**
     * Add a filter to exclude from all fields and associations
     */
    public function excludeFilter(Filters $filter): self
    {
        $this->excludeFilters[] = $filter;

        return $this;
    }

    /**
     * Build and return the Config instance
     */
    public function build(): Config
    {
        return new Config([
            'group' => $this->group,
            'groupSuffix' => $this->groupSuffix,
            'useHydratorCache' => $this->useHydratorCache,
            'useQueryResultCache' => $this->useQueryResultCache,
            'limit' => $this->limit,
            'globalEnable' => $this->globalEnable,
            'ignoreFields' => $this->ignoreFields,
            'globalByValue' => $this->globalByValue,
            'entityPrefix' => $this->entityPrefix,
            'sortFields' => $this->sortFields,
            'excludeFilters' => $this->excludeFilters,
        ]);
    }
}
