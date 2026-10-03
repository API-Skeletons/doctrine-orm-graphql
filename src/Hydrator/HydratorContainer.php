<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\ComputedFieldMetadata;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Closure;
use Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use Laminas\Hydrator\NamingStrategy\MapNamingStrategy;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Override;
use ReflectionClass;

use function array_key_exists;
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
                $entityManager  = $self->entityManager;
                $entity         = $self->entityTypeContainer->get($id);
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

                    $strategy = $self->get($fieldMetadata->hydratorStrategy);
                    assert($strategy instanceof StrategyInterface);

                    // The hydrator sets the collection name and class metadata
                    // on a collection strategy, so each collection has its own
                    if ($strategy instanceof CollectionStrategyInterface) {
                        $strategy = clone $strategy;
                    }

                    $object->addStrategy($fieldName, $strategy);
                }

                // Register computed fields
                foreach ($entityMetadata->computedFields as $fieldName => $computedFieldMetadata) {
                    $object->addComputedField(
                        $fieldName,
                        self::computedFieldExtractor($computedFieldMetadata),
                        $computedFieldMetadata->args !== [],
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

    /**
     * A function which calls a computed field's method with the field's
     * arguments, by name.  An argument which is not given is left to the
     * method's default value, or is null when the method has none.
     *
     * @return Closure(object, array<array-key, mixed>): mixed
     */
    private static function computedFieldExtractor(ComputedFieldMetadata $computedFieldMetadata): Closure
    {
        $methodName = $computedFieldMetadata->method;
        $arguments  = $computedFieldMetadata->args;

        return static function (object $entity, array $args) use ($methodName, $arguments): mixed {
            $named = [];
            foreach ($arguments as $name => $argument) {
                if (array_key_exists($name, $args)) {
                    /** @psalm-suppress MixedAssignment An argument may be of any type */
                    $named[$name] = $args[$name];
                } elseif ($argument->default === null) {
                    $named[$name] = null;
                }
            }

            /** @psalm-suppress MixedMethodCall */
            return $entity->$methodName(...$named);
        };
    }
}
