<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
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
            ->set(Strategy\ToInteger::class, static fn () => new Strategy\ToInteger());
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
                $metadata      = $entity->getMetadata();
                $byValue       = $metadata['byValue'];

                /** @psalm-suppress DirectConstructorCall */
                $object->__construct(
                    $entityManager,
                    $byValue,
                );

                // Create field strategy and assign to hydrator
                foreach ($metadata['fields'] as $fieldName => $fieldMetadata) {
                    $implements = class_implements($fieldMetadata['hydratorStrategy']);
                    assert(
                        in_array(StrategyInterface::class, $implements !== false ? $implements : []),
                        'Strategy must implement ' . StrategyInterface::class,
                    );

                    $object->addStrategy($fieldName, $self->get($fieldMetadata['hydratorStrategy']));
                }

                // Register computed fields
                if (isset($metadata['computedFields'])) {
                    foreach ($metadata['computedFields'] as $fieldName => $computedFieldMetadata) {
                        $methodName = $computedFieldMetadata['method'];

                        // Create extractor closure that calls the entity method
                        $object->addComputedField(
                            $fieldName,
                            static fn (object $entity): mixed => $entity->$methodName(),
                        );
                    }
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
