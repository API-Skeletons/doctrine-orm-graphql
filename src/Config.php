<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use BadMethodCallException;
use InvalidArgumentException;

use function array_merge;
use function lcfirst;
use function property_exists;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * This class is used for setting parameters when
 * creating the driver
 */
readonly class Config
{
    /**
     * @var string The GraphQL group. This allows multiple GraphQL
     *             configurations within the same application or
     *             even within the same group of entities and Object Manager.
     */
    protected string $group;

    /**
     * @var string|null The group is usually suffixed to GraphQL type names.
     *                  You may specify a different string for the group suffix
     *                  or you my supply an empty string to exclude the suffix.
     *                  Be warned, using the same groupSuffix with two different
     *                  groups can cause collisions.
     */
    protected string|null $groupSuffix;

    /**
     * @var bool When set to true hydrator results will be cached for the
     *           duration of the request thereby saving multiple extracts for
     *           the same entity.
     */
    protected bool $useHydratorCache;

    /** @var int A hard limit for fetching any collection within the schema */
    protected int $limit;

    /**
     * @var bool When set to true all fields and all associations will be
     *           enabled.  This is best used as a development setting when
     *           the entities are subject to change.
     */
    protected bool $globalEnable;

    /** @var string[] An array of field names to ignore when using globalEnable. */
    protected array $ignoreFields;

    /**
     * @var bool|null When set to true, all entities will be extracted by value
     *                across all hydrators in the driver.  When set to false,
     *                all hydrators will extract by reference.  This overrides
     *                per-entity attribute configuration.
     */
    protected bool|null $globalByValue;

    /**
     * @var string|null When set, the entityPrefix will be removed from each
     *                  type name.  This simplifies type names and makes reading
     *                  the GraphQL documentation easier.
     */
    protected string|null $entityPrefix;

    /**
     * @var bool|null When set to true entity fields will be
     *                sorted alphabetically
     */
    protected bool|null $sortFields;

    /**
     * @var Filters[] An array of filters to exclude from
     *                available filters for all fields and
     *                associations in every entity
     */
    protected array $excludeFilters;

    /** @param mixed[] $config */
    public function __construct(array $config = [])
    {
        $defaults = [
            'group'            => 'default',
            'groupSuffix'      => null,
            'useHydratorCache' => false,
            'limit'            => 1000,
            'globalEnable'     => false,
            'ignoreFields'     => [],
            'globalByValue'    => null,
            'entityPrefix'     => null,
            'sortFields'       => null,
            'excludeFilters'   => [],
        ];

        $mergedConfig = array_merge($defaults, $config);

        foreach ($mergedConfig as $field => $value) {
            if (! property_exists($this, $field)) {
                throw new InvalidArgumentException('Invalid configuration setting: ' . $field);
            }

            $this->$field = $value;
        }
    }

    /**
     * Magic getter
     *
     * @param mixed[] $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $property = lcfirst(substr($name, 3));

        if (str_starts_with($name, 'get') && property_exists($this, $property)) {
            return $this->$property;
        }

        throw new BadMethodCallException(sprintf('Method %s does not exist', $name));
    }
}
