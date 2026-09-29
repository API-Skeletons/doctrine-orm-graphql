<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeNotFound as TypeNotFoundException;
use Closure;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type as GraphQLType;

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

        $typeContainer = $this->service(Type\TypeContainer::class);

        /** @psalm-suppress MixedReturnStatement */
        return $typeContainer->build(Type\Connection::class, Type\Connection::nameFor($objectType->name), $objectType);
    }

    /**
     * A shortcut into the EntityTypeContainer and TypeContainer
     *
     * @throws TypeNotFoundException
     */
    public function type(string $id, string|null $eventName = null): mixed
    {
        $entityTypeContainer = $this->service(Type\Entity\EntityTypeContainer::class);
        if ($entityTypeContainer->has($id)) {
            $entity = $entityTypeContainer->get($id, $eventName);

            return $entity->getObjectType();
        }

        $typeContainer = $this->service(Type\TypeContainer::class);
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
        $filterFactory       = $this->service(Filter\FilterFactory::class);
        $entityTypeContainer = $this->service(Type\Entity\EntityTypeContainer::class);
        $entity              = $entityTypeContainer->get($id);

        return $filterFactory->get($entity);
    }

    /**
     * The pagination arguments for a connection: first, after, last and before.
     * Add them to the top level of a connection field's args.
     *
     * @return array<string, array{type: GraphQLType, description: string}>
     */
    public function pagination(): array
    {
        $paginationService = $this->service(Pagination\PaginationService::class);

        return $paginationService->getArguments();
    }

    /**
     * Resolve a connection
     *
     * @throws Error
     */
    public function resolve(string $id, string|null $eventName = null): Closure
    {
        $resolveEntityFactory = $this->service(Resolve\ResolveEntityFactory::class);
        $entityTypeContainer  = $this->service(Type\Entity\EntityTypeContainer::class);
        $entity               = $entityTypeContainer->get($id);

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
        $typeContainer = $this->service(Type\TypeContainer::class);

        $connection = $typeContainer->build(Type\Connection::class, Type\Connection::nameFor($type->name), $type);
        assert($connection instanceof ObjectType);

        return $connection;
    }

    /**
     * Resolve a connection from a DBAL QueryBuilder.  The QueryBuilder is
     * given the offset and limit calculated from the pagination arguments.
     * Used in conjunction with dbalConnection()
     *
     * @throws Error
     */
    public function dbalResolve(DbalQueryBuilder $queryBuilder): Closure
    {
        $resolveDbalFactory = $this->service(Resolve\ResolveDbalFactory::class);

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
            'args' => $this->pagination(),
            'resolve' => $this->dbalResolve($queryBuilder),
        ];
    }

    /**
     * Return an InputObjectType for a mutation.  The same entity and fields
     * always return the same type.
     *
     * @param string[]    $requiredFields An optional list of just the required fields you want for the mutation.
     * @param string[]    $optionalFields An optional list of optional fields you want for the mutation.
     * @param string|null $name           An optional name for the input type.  When it is not given the
     *                                    name is derived from the entity and the fields.
     */
    public function input(
        string $entityClass,
        array $requiredFields = [],
        array $optionalFields = [],
        string|null $name = null,
    ): InputObjectType {
        $inputFactory = $this->service(Input\InputFactory::class);

        return $inputFactory->get($entityClass, $requiredFields, $optionalFields, $name);
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
            'args' => ['filter' => $this->filter($id)] + $this->pagination(),
            'resolve' => $this->resolve($id, $resolveEventName),
        ];
    }
}
