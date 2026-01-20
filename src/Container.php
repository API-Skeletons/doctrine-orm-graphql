<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound as TypeNotFoundException;
use Closure;
use GraphQL\Error\Error;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

use function array_keys;
use function assert;
use function strtolower;

/**
 * Used in many places to create a container of types and services
 */
abstract class Container implements ContainerInterface
{
    /** @var mixed[] */
    protected array $register = [];

    #[Override]
    public function has(string $id): bool
    {
        return isset($this->register[strtolower($id)]);
    }

    /** @throws TypeNotFoundException */
    #[Override]
    public function get(string $id): mixed
    {
        $originalId = $id;
        $id         = strtolower($id);

        if (! $this->has($id)) {
            throw new TypeNotFoundException(
                typeId: $originalId,
                availableTypes: array_keys($this->register),
            );
        }

        if ($this->register[$id] instanceof Closure) {
            $closure = $this->register[$id];

            $this->register[$id] = $closure($this);
        }

        return $this->register[$id];
    }

    /**
     * This allows for a duplicate id to overwrite an existing registration
     */
    public function set(string $id, mixed $value): self
    {
        $id = strtolower($id);

        $this->register[$id] = $value;

        return $this;
    }

    /**
     * Get all registered type IDs
     *
     * @return string[]
     */
    public function getRegisteredTypes(): array
    {
        return array_keys($this->register);
    }

    /**
     * This function allows for buildable types.  The Type\Connection type is created this way
     * because it relies on the entity object type.  To create a custom buildable object type
     * it must implement the Buildable interface.
     *
     * @param class-string $className
     *
     * @throws Error
     * @throws ReflectionException
     */
    public function build(string $className, string $typeName, mixed ...$params): mixed
    {
        if ($this->has($typeName)) {
            return $this->get($typeName);
        }

        $reflectionClass = new ReflectionClass($className);
        assert($reflectionClass->implementsInterface(Buildable::class));

        return $this
            ->set($typeName, new $className($this, $typeName, $params))
            ->get($typeName);
    }
}
