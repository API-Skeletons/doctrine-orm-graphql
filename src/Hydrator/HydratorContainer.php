<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Container;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use Laminas\Hydrator\NamingStrategy\MapNamingStrategy;
use Laminas\Hydrator\Strategy\StrategyInterface;

use function assert;
use function class_implements;
use function in_array;

/**
 * This factory is used in the Metadata\Entity class to create a hydrator
 * for the current entity
 */
class HydratorContainer extends Container
{
    public function __construct(
        protected EntityManager $entityManager,
        protected EntityTypeContainer $entityTypeContainer,
    ) {
        // Register default strategies
        $this
            ->set(Strategy\AssociationDefault::class, new Strategy\AssociationDefault())
            ->set(Strategy\FieldDefault::class, new Strategy\FieldDefault())
            ->set(Strategy\ToBoolean::class, new Strategy\ToBoolean())
            ->set(Strategy\ToFloat::class, new Strategy\ToFloat())
            ->set(Strategy\ToInteger::class, new Strategy\ToInteger());
    }

    /** @throws Error */
    public function get(string $id): mixed
    {
        if ($this->has($id)) {
            return parent::get($id);
        }

        $entity   = $this->entityTypeContainer->get($id);
        $metadata = $entity->getMetadata();
        $hydrator = new DoctrineObject($this->entityManager, $metadata['byValue']);

        // Create field strategy and assign to hydrator
        foreach ($metadata['fields'] as $fieldName => $fieldMetadata) {
            assert(
                in_array(StrategyInterface::class, class_implements($fieldMetadata['hydratorStrategy'])),
                'Strategy must implement ' . StrategyInterface::class,
            );

            $hydrator->addStrategy($fieldName, $this->get($fieldMetadata['hydratorStrategy']));
        }

        // Create naming strategy for aliases and assign to hydrator
        if ($entity->getExtractionMap()) {
            $hydrator->setNamingStrategy(
                MapNamingStrategy::createFromExtractionMap($entity->getExtractionMap()),
            );
        }

        $this->set($id, $hydrator);

        return $hydrator;
    }
}
