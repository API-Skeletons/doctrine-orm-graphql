<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use InvalidArgumentException;

use function array_merge;
use function property_exists;

/**
 * This class is used for setting parameters when
 * creating the driver
 */
class Config
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
     * @var bool When set to true hydrator results will be cached for the
     *           duration of the request thereby saving multiple extracts for
     *           the same entity.
     */
    protected readonly bool $useHydratorCache;

    /** @var int A hard limit for fetching any collection within the schema */
    protected readonly int $limit;

    /**
     * @var bool When set to true all fields and all associations will be
     *           enabled.  This is best used as a development setting when
     *           the entities are subject to change.
     */
    protected readonly bool $globalEnable;

    /** @var string[] An array of field names to ignore when using globalEnable. */
    protected readonly array $ignoreFields;

    /**
     * @var bool|null When set to true, all entities will be extracted by value
     *                across all hydrators in the driver.  When set to false,
     *                all hydrators will extract by reference.  This overrides
     *                per-entity attribute configuration.
     */
    protected readonly bool|null $globalByValue;

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
            'limit' => 1000,
            'globalEnable' => false,
            'ignoreFields' => [],
            'globalByValue' => null,
            'entityPrefix' => null,
            'sortFields' => null,
            'excludeFilters' => [],
        ];

        $mergedConfig = array_merge($default, $config);

        foreach ($mergedConfig as $field => $value) {
            if (! property_exists($this, $field)) {
                throw new InvalidArgumentException('Invalid configuration setting: ' . $field);
            }
        }

        // Assigning properties explicitly is phpstan friendly
        $this->group            = $mergedConfig['group'];
        $this->groupSuffix      = $mergedConfig['groupSuffix'];
        $this->useHydratorCache = $mergedConfig['useHydratorCache'];
        $this->limit            = $mergedConfig['limit'];
        $this->globalEnable     = $mergedConfig['globalEnable'];
        $this->ignoreFields     = $mergedConfig['ignoreFields'];
        $this->globalByValue    = $mergedConfig['globalByValue'];
        $this->entityPrefix     = $mergedConfig['entityPrefix'];
        $this->sortFields       = $mergedConfig['sortFields'];
        $this->excludeFilters   = $mergedConfig['excludeFilters'];
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

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getGlobalEnable(): bool
    {
        return $this->globalEnable;
    }

    /** @return string[] */
    public function getIgnoreFields(): array
    {
        return $this->ignoreFields;
    }

    public function getGlobalByValue(): bool|null
    {
        return $this->globalByValue;
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
