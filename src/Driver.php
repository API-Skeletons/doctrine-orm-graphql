<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Closure;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;

class Driver extends AbstractContainer
{
    use Services;

    /**
     * Return a connection wrapper for a type
     *
     * @throws Error
     */
    public function connection(ObjectType $objectType): ObjectType
    {
        /**
         * Connections rely on the entity ObjectType so the build() method is used
         */
        return $this->get(Type\TypeManager::class)
            ->build(Type\Connection::class, $objectType->name . '_Connection', $objectType);
    }

    /**
     * Return a GraphQL type for the entity class
     *
     * @throws Error
     */
    public function type(string $entityClass): ObjectType
    {
        return $this->get(Type\TypeManager::class)->build(Type\Entity::class, $entityClass)();
    }

    /**
     * Filters for a connection
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
    public function resolve(string $entityClass, string $eventName = 'filter.querybuilder'): Closure
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
