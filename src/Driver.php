<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;

class Driver extends AbstractContainer
{
    use Services;

    /**
     * Return a connection wrapper for a type.  This is a special type that
     * wraps the entity type
     *
     * @throws Error
     */
    public function connection(ObjectType $objectType): ObjectType
    {
        return $this->get(Type\TypeManager::class)
            ->build(Type\Connection::class, $objectType->name . '_Connection', $objectType);
    }

    /**
     * A shortcut into the TypeManager that also handles Entity objects
     *
     * @throws Error
     */
    public function type(string $typeName): mixed
    {
        $typeManager = $this->get(Type\TypeManager::class);

        try {
            // If a type is not registered, try to resolve it as an Entity
            if (! $typeManager->has($typeName)) {
                return $this->entityType($typeName)();
            }

            $type = $typeManager->get($typeName);

            // Resolve an Entity type to its GraphQL representation
            if ($type instanceof Entity) {
                return $type();
            }
        } catch (Error) {
            throw new Error('Type "' . $typeName . '" is not registered');
        }

        return $type;
    }

    /**
     * Return an InputObject type of filters for a connection
     * Requires the internal representation of the entity
     *
     * @throws Error
     */
    public function filter(string $entityClass): object
    {
        return $this->get(Filter\FilterFactory::class)->get(
            $this->entityType($entityClass),
        );
    }

    /**
     * Pagination for a connection
     *
     * @throws Error
     */
    public function pagination(): object
    {
        return $this->type('pagination');
    }

    /**
     * Resolve a connection
     *
     * @throws Error
     */
    public function resolve(string $entityClass, string|null $eventName = null): Closure
    {
        return $this->get(Resolve\ResolveEntityFactory::class)->get(
            $this->entityType($entityClass),
            $eventName,
        );
    }

    /**
     * @param string[] $requiredFields An optional list of just the required fields you want for the mutation.
     *                              This allows specific fields per mutation.
     * @param string[] $optionalFields An optional list of optional fields you want for the mutation.
     *                              This allows specific fields per mutation.
     */
    public function input(string $entityClass, array $requiredFields = [], array $optionalFields = []): InputObjectType
    {
        return $this->get(Input\InputFactory::class)->get($entityClass, $requiredFields, $optionalFields);
    }

    /**
     * Internally an Entity object is used for Doctrine entities.
     * The Entity object has an __invoke method which returns the
     * GraphQL ObjectType.  This method exists to fetch that Entity
     * object.  It is resolved by $this->type()
     *
     * Access to this method is not recommended.  It is used internally
     * but requires public scope.
     *
     * @throws Error
     */
    public function entityType(string $entityClass): Entity
    {
        return $this->get(Type\TypeManager::class)
            ->build(Type\Entity::class, $entityClass);
    }
}
