<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Filter\Password;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\ORM\EntityManager;
use GraphQL\Error\Error;
use Laminas\Hydrator\Filter;
use Laminas\Hydrator\NamingStrategy\NamingStrategyEnabledInterface;
use Laminas\Hydrator\NamingStrategy\NamingStrategyInterface;
use Laminas\Hydrator\Strategy\StrategyEnabledInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;

use function assert;
use function class_implements;
use function in_array;

/**
 * This factory is used in the Metadata\Entity class to create a hydrator
 * for the current entity
 */
class HydratorFactory extends AbstractContainer
{
    public function __construct(protected EntityManager $entityManager, protected TypeManager $typeManager)
    {
        // Register project defaults
        $this
            ->set(Strategy\AssociationDefault::class, new Strategy\AssociationDefault())
            ->set(Strategy\FieldDefault::class, new Strategy\FieldDefault())
            ->set(Strategy\NullifyOwningAssociation::class, new Strategy\NullifyOwningAssociation())
            ->set(Strategy\ToBoolean::class, new Strategy\ToBoolean())
            ->set(Strategy\ToFloat::class, new Strategy\ToFloat())
            ->set(Strategy\ToInteger::class, new Strategy\ToInteger())
            ->set(Password::class, new Password());
    }

    /** @throws Error */
    public function get(string $id): mixed
    {
        // Custom hydrators should already be registered
        if ($this->has($id)) {
            return parent::get($id);
        }

        $entity   = $this->typeManager->build(Entity::class, $id);
        $config   = $entity->getMetadata();
        $hydrator = new DoctrineObject($this->entityManager, $config['byValue']);

        // Create field strategy and assign to hydrator
        if ($hydrator instanceof StrategyEnabledInterface) {
            foreach ($config['fields'] as $fieldName => $fieldMetadata) {
                assert(
                    in_array(StrategyInterface::class, class_implements($fieldMetadata['strategy'])),
                    'Strategy must implement ' . StrategyInterface::class,
                );

                $hydrator->addStrategy($fieldName, $this->get($fieldMetadata['strategy']));
            }
        }

        // Create filters and assign to hydrator
        if ($hydrator instanceof Filter\FilterEnabledInterface) {
            foreach ($config['filters'] as $name => $filterConfig) {
                // Default filters to AND
                $condition   = $filterConfig['condition'] ?? Filter\FilterComposite::CONDITION_AND;
                $filterClass = $filterConfig['filter'];
                assert(
                    in_array(Filter\FilterInterface::class, class_implements($filterClass)),
                    'Filter must implement ' . StrategyInterface::class,
                );

                $hydrator->addFilter($name, $this->get($filterClass), $condition);
            }
        }

        // Create naming strategy and assign to hydrator
        if ($hydrator instanceof NamingStrategyEnabledInterface && $config['namingStrategy']) {
            $namingStrategyClass = $config['namingStrategy'];

            assert(
                in_array(NamingStrategyInterface::class, class_implements($namingStrategyClass)),
                'Naming Strategy must implement ' . NamingStrategyInterface::class,
            );

            $hydrator->setNamingStrategy($this->get($namingStrategyClass));
        }

        $this->set($id, $hydrator);

        return $hydrator;
    }
}
