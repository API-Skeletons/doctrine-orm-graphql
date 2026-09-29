<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use Laminas\Hydrator\NamingStrategy\MapNamingStrategy;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Override;
use ReflectionClass;

use function assert;
use function class_implements;
use function in_array;

/**
 * This factory is used in the Metadata\Entity class to create a hydrator
 * for the current entity
 */
final class HydratorContainer extends Container
{
    public function __construct(
        protected readonly EntityManager $entityManager,
        protected readonly EntityTypeContainer $entityTypeContainer,
    ) {
        // Register default strategies
        $this
            ->set(Strategy\AssociationDefault::class, static fn () => new Strategy\AssociationDefault())
            ->set(Strategy\FieldDefault::class, static fn () => new Strategy\FieldDefault())
            ->set(Strategy\ToBoolean::class, static fn () => new Strategy\ToBoolean())
            ->set(Strategy\ToFloat::class, static fn () => new Strategy\ToFloat())
            ->set(Strategy\ToInteger::class, static fn () => new Strategy\ToInteger())
            ->set(Strategy\ToString::class, static fn () => new Strategy\ToString());
    }

    /** @throws Error */
    #[Override]
    public function get(string $id): mixed
    {
        if ($this->has($id)) {
            return parent::get($id);
        }

        $self = $this;
        // Compose hydrators as Lazy Ghosts using DoctrineObjectWithComputed
        $hydrator = (new ReflectionClass(DoctrineObjectWithComputed::class))
            ->newLazyGhost(static function (DoctrineObjectWithComputed $object) use ($self, $id): void {
                $entityManager = $self->entityManager;
                $entity        = $self->entityTypeContainer->get($id);
                assert($entity instanceof Entity);
                $entityMetadata = $entity->getEntityMetadata();

                /** @psalm-suppress DirectConstructorCall */
                $object->__construct(
                    $entityManager,
                    $entityMetadata->extractByValue,
                );

                // Create field and association strategies and assign them to the hydrator
                $fields = [...$entityMetadata->fields, ...$entityMetadata->associations];
                foreach ($fields as $fieldName => $fieldMetadata) {
                    $implements = class_implements($fieldMetadata->hydratorStrategy);
                    if (! in_array(StrategyInterface::class, $implements !== false ? $implements : [])) {
                        throw new HydratorException(
                            'Hydrator strategy ' . $fieldMetadata->hydratorStrategy . ' for field ' . $fieldName
                            . ' of entity ' . $entity->getEntityClass() . ' must implement ' . StrategyInterface::class,
                        );
                    }

                    /** @psalm-suppress MixedArgument */
                    $object->addStrategy($fieldName, $self->get($fieldMetadata->hydratorStrategy));
                }

                // Register computed fields
                foreach ($entityMetadata->computedFields as $fieldName => $computedFieldMetadata) {
                    $methodName = $computedFieldMetadata->method;

                    // Create extractor closure that calls the entity method
                    /** @psalm-suppress MixedMethodCall */
                    $object->addComputedField(
                        $fieldName,
                        static fn (object $entity): mixed => $entity->$methodName(),
                    );
                }

                // Create naming strategy for aliases and assign to hydrator
                if (! $entity->getExtractionMap()) {
                    return;
                }

                $object->setNamingStrategy(
                    MapNamingStrategy::createFromExtractionMap($entity->getExtractionMap()),
                );
            });

        $this->set($id, $hydrator);

        return $hydrator;
    }
}
