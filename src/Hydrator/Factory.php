<?php

/**
 * This class is an edit of phpro/zf-doctrine-hydrator-module
 */

namespace ApiSkeletons\Doctrine\GraphQL\Hydrator;

use ApiSkeletons\Doctrine\GraphQL\Invokable;
use ApiSkeletons\Doctrine\GraphQL\Type\Entity;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\ORM\EntityManager;
use Laminas\Hydrator\Filter\FilterComposite;
use Laminas\Hydrator\Filter\FilterEnabledInterface;
use Laminas\Hydrator\Filter\FilterInterface;
use Laminas\Hydrator\HydratorInterface;
use Laminas\Hydrator\NamingStrategy\NamingStrategyEnabledInterface;
use Laminas\Hydrator\NamingStrategy\NamingStrategyInterface;
use Laminas\Hydrator\Strategy\StrategyEnabledInterface;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Psr\Container\ContainerInterface;

/**
 * This factory is used in the Metadata\Entity class to create a hydrator
 * for the current entity
 */
class Factory
{
    protected ContainerInterface $container;

    public function __invoke(ContainerInterface $container, EntityManager $entityManager, Entity $entity): HydratorInterface
    {
        $this->container = $container;
        $config = $entity->getMetadataContent();

        $hydratorClass = $config['hydrator'];

        assert(in_array(HydratorInterface::class, class_implements($hydratorClass)),
            'Hydrator must implement ' . HydratorInterface::class
        );

        if ($hydratorClass === 'default') {
            $hydrator = new DoctrineObject($entityManager, $config['byValue']);
        } else {
            // FIXME:  How can this be improved?  Would like to pass the config to the container :\
            // It may be that each entity must have a unique hydrator
            $hydrator = $this->get($hydratorClass);
        }

        // Create strategies and assign to hydrator
        if ($hydrator instanceof StrategyEnabledInterface) {
            foreach ($config['strategies'] as $fieldName => $strategyClass) {
                assert(in_array(StrategyInterface::class, class_implements($strategyClass)),
                    'Strategy must implement ' . StrategyInterface::class
                );

                $hydrator->addStrategy($fieldName, $this->get($strategyClass));
            }
        }

        // Create filters and assign to hydrator
        if ($hydrator instanceof FilterEnabledInterface) {
            foreach ($config['filters'] as $name => $filterConfig) {
                // Default filters to AND
                $condition = $filterConfig['condition'] ?? FilterComposite::CONDITION_AND;
                $filterClass = $filterConfig['filter'];
                assert(in_array(FilterInterface::class, class_implements($filterClass)),
                    'Filter must implement ' . StrategyInterface::class
                );

                $hydrator->addFilter($name, $this->get($filterClass), $condition);
            }
        }

        // Create naming strategy and assign to hydrator
        if ($hydrator instanceof NamingStrategyEnabledInterface && $config['namingStrategy']) {
            $namingStrategyClass = $config['naming_strategy'];

            assert(in_array(NamingStrategyInterface::class, class_implements($namingStrategyClass)),
                'Naming Strategy must implement ' . NamingStrategyInterface::class
            );

            $hydrator->setNamingStrategy($this->get($namingStrategyClass));
        }

        return $hydrator;
    }

    /**
     * Instead of using the container for all objects, such as included filters
     * and strategies, which should not be required to load into the service
     * manager (affects some frameworks, not others), an Invokable interface
     * exists to flag the class to be created directly.
     *
     * @param $className
     * @return mixed
     */
    protected function get($className)
    {
        if (in_array(Invokable::class, class_implements($className))) {
            $class = new $className();
        } else {
            $class = $this->container->get($className);
        }

        return $class;
    }
}
