<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;

use GraphQL\Type\Definition\ScalarType;
use function method_exists;

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
     * A shortcut into the TypeManager
     *
     * This handles the special case for types that are both a GraphQL type
     * and a PHP type by resolving the __invoke method.
     *
     * @throws Error
     */
    public function type(string $typeName): ObjectType|ScalarType
    {
        $typeManager = $this->get(Type\TypeManager::class);

        if (! $typeManager->has($typeName)) {
            return $typeManager->build(Type\Entity::class, $typeName)();
        }

        $type = $typeManager->get($typeName);

        if (method_exists($type, '__invoke')) {
            return $type();
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
            $this->get(Type\TypeManager::class)
               ->build(Type\Entity::class, $entityClass),
        );
    }

    /**
     * Pagination for a connection
     *
     * @throws Error
     */
    public function pagination(): object
    {
        return $this->get(TypeManager::class)->get('pagination');
    }

    /**
     * Resolve a connection
     *
     * @throws Error
     */
    public function resolve(string $entityClass, string|null $eventName = null): Closure
    {
        return $this->get(Resolve\ResolveEntityFactory::class)->get(
            $this->get(Type\TypeManager::class)
                ->build(Type\Entity::class, $entityClass),
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
}
