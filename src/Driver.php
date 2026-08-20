<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound as TypeNotFoundException;
use Closure;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;

use function array_merge;
use function assert;

final class Driver extends Container
{
    use Services;

    /**
     * Return a connection wrapper for a type.  This is a special type that
     * wraps the entity type
     *
     * @throws Error
     */
    public function connection(string $id, string|null $eventName = null): ObjectType
    {
        $objectType = $this->type($id, $eventName);
        assert($objectType instanceof ObjectType);

        $typeContainer = $this->get(Type\TypeContainer::class);
        assert($typeContainer instanceof Type\TypeContainer);

        /** @psalm-suppress MixedReturnStatement */
        return $typeContainer->build(Type\Connection::class, $objectType->name, $objectType);
    }

    /**
     * A shortcut into the EntityTypeContainer and TypeContainer
     *
     * @throws TypeNotFoundException
     */
    public function type(string $id, string|null $eventName = null): mixed
    {
        $entityTypeContainer = $this->get(Type\Entity\EntityTypeContainer::class);
        assert($entityTypeContainer instanceof Type\Entity\EntityTypeContainer);
        if ($entityTypeContainer->has($id)) {
            $entity = $entityTypeContainer->get($id, $eventName);
            assert($entity instanceof Type\Entity\Entity);

            return $entity->getObjectType();
        }

        $typeContainer = $this->get(Type\TypeContainer::class);
        assert($typeContainer instanceof Type\TypeContainer);
        if ($typeContainer->has($id)) {
            return $typeContainer->get($id);
        }

        // Collect all available types from both containers
        $availableTypes = array_merge(
            $entityTypeContainer->getRegisteredTypes(),
            $typeContainer->getRegisteredTypes(),
        );

        $suggestion = $this->findSimilarString($id, $availableTypes);

        throw new TypeNotFoundException(
            typeId: $id,
            availableTypes: $availableTypes,
            suggestion: $suggestion,
        );
    }

    /**
     * Return an InputObject type of filters for a connection
     * Requires the internal representation of the entity
     *
     * @throws Error
     */
    public function filter(string $id): object
    {
        $filterFactory = $this->get(Filter\FilterFactory::class);
        assert($filterFactory instanceof Filter\FilterFactory);
        $entityTypeContainer = $this->get(Type\Entity\EntityTypeContainer::class);
        assert($entityTypeContainer instanceof Type\Entity\EntityTypeContainer);
        $entity = $entityTypeContainer->get($id);
        assert($entity instanceof Type\Entity\Entity);

        return $filterFactory->get($entity);
    }

    /**
     * Pagination for a connection
     *
     * @throws Error
     */
    public function pagination(): object
    {
        $result = $this->type('pagination');
        assert($result instanceof InputObjectType);

        return $result;
    }

    /**
     * Resolve a connection
     *
     * @throws Error
     */
    public function resolve(string $id, string|null $eventName = null): Closure
    {
        $resolveEntityFactory = $this->get(Resolve\ResolveEntityFactory::class);
        assert($resolveEntityFactory instanceof Resolve\ResolveEntityFactory);
        $entityTypeContainer = $this->get(Type\Entity\EntityTypeContainer::class);
        assert($entityTypeContainer instanceof Type\Entity\EntityTypeContainer);
        $entity = $entityTypeContainer->get($id);
        assert($entity instanceof Type\Entity\Entity);

        return $resolveEntityFactory->get($entity, $eventName);
    }

    /**
     * Return a connection wrapper for a type resolved from a DBAL QueryBuilder.
     * Used in conjunction with dbalResolve()
     *
     * @throws Error
     */
    public function dbalConnection(ObjectType $type): ObjectType
    {
        $typeContainer = $this->get(Type\TypeContainer::class);
        assert($typeContainer instanceof Type\TypeContainer);

        $connection = $typeContainer->build(Type\Connection::class, $type->name, $type);
        assert($connection instanceof ObjectType);

        return $connection;
    }

    /**
     * Resolve a connection from a DBAL QueryBuilder.  The QueryBuilder is
     * given the offset and limit calculated from the pagination argument.
     * Used in conjunction with dbalConnection()
     *
     * @throws Error
     */
    public function dbalResolve(DbalQueryBuilder $queryBuilder): Closure
    {
        $resolveDbalFactory = $this->get(Resolve\ResolveDbalFactory::class);
        assert($resolveDbalFactory instanceof Resolve\ResolveDbalFactory);

        return $resolveDbalFactory->get($queryBuilder);
    }

    /**
     * Return an array defining a GraphQL endpoint for a DBAL QueryBuilder.
     * This is a short cut to using dbalConnection(), pagination(), and dbalResolve().
     *
     * @return mixed[]
     *
     * @throws Error
     */
    public function dbalCompleteConnection(ObjectType $type, DbalQueryBuilder $queryBuilder): array
    {
        return [
            'type' => $this->dbalConnection($type),
            'args' => ['pagination' => $this->pagination()],
            'resolve' => $this->dbalResolve($queryBuilder),
        ];
    }

    /**
     * @param string[] $requiredFields An optional list of just the required fields you want for the mutation.
     * @param string[] $optionalFields An optional list of optional fields you want for the mutation.
     */
    public function input(string $entityClass, array $requiredFields = [], array $optionalFields = []): InputObjectType
    {
        $inputFactory = $this->get(Input\InputFactory::class);
        assert($inputFactory instanceof Input\InputFactory);

        return $inputFactory->get($entityClass, $requiredFields, $optionalFields);
    }

    /**
     * Return an array defining a GraphQL endpoint.
     *
     * @return mixed[]
     */
    public function completeConnection(
        string $id,
        string|null $entityDefinitionEventName = null,
        string|null $resolveEventName = null,
    ): array {
        return [
            'type' => $this->connection($id, $entityDefinitionEventName),
            'args' => [
                'filter' => $this->filter($id),
                'pagination' => $this->pagination(),
            ],
            'resolve' => $this->resolve($id, $resolveEventName),
        ];
    }
}
