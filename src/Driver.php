<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

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
    public function connection(string $id): ObjectType
    {
        $objectType = $this->type($id);

        return $this->get(Type\TypeContainer::class)
            ->build(Type\Connection::class, $objectType->name, $objectType);
    }

    /**
     * A shortcut into the EntityTypeContainer and TypeContainer
     *
     * @throws Error
     */
    public function type(string $id): mixed
    {
        $entityTypeContainer = $this->get(Type\Entity\EntityTypeContainer::class);
        if ($entityTypeContainer->has($id)) {
            return $entityTypeContainer->get($id)->getObjectType();
        }

        $typeContainer = $this->get(Type\TypeContainer::class);
        if ($typeContainer->has($id)) {
            return $typeContainer->get($id);
        }

        throw new Error('Type "' . $id . '" is not registered');
    }

    /**
     * Return an InputObject type of filters for a connection
     * Requires the internal representation of the entity
     *
     * @throws Error
     */
    public function filter(string $id): object
    {
        return $this->get(Filter\FilterFactory::class)->get(
            $this->get(Type\Entity\EntityTypeContainer::class)->get($id),
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
    public function resolve(string $id, string|null $eventName = null): Closure
    {
        return $this->get(Resolve\ResolveEntityFactory::class)->get(
            $this->get(Type\Entity\EntityTypeContainer::class)->get($id),
            $eventName,
        );
    }

    /**
     * @param string[] $requiredFields An optional list of just the required fields you want for the mutation.
     * @param string[] $optionalFields An optional list of optional fields you want for the mutation.
     */
    public function input(string $entityClass, array $requiredFields = [], array $optionalFields = []): InputObjectType
    {
        return $this->get(Input\InputFactory::class)->get($entityClass, $requiredFields, $optionalFields);
    }
}
