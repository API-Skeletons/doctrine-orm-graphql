<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Hydrator as HydratorException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

use function ini_get;
use function ini_set;

/**
 * A hydratorStrategy which is not a strategy is an error whether or not
 * assertions are enabled
 */
class InvalidHydratorStrategyTest extends TestCase
{
    public function testStrategyWhichIsNotAStrategyThrowsHydratorException(): void
    {
        $previous = ini_get('zend.assertions');
        // zend.assertions=-1 cannot be changed at runtime; assertions are already off
        if ($previous !== '-1') {
            ini_set('zend.assertions', '0');
        }

        try {
            $driver = new Driver($this->getEntityManager(), new Config(['group' => 'InvalidHydratorStrategyTest']));
            $user   = $this->getEntityManager()->getRepository(User::class)->find(1);

            $this->expectException(HydratorException::class);
            $this->expectExceptionMessage(
                'Hydrator strategy stdClass for field email of entity ' . User::class
                . ' must implement Laminas\Hydrator\Strategy\StrategyInterface',
            );

            $driver->get(HydratorContainer::class)->get(User::class)->extract($user);
        } finally {
            if ($previous !== '-1') {
                ini_set('zend.assertions', (string) $previous);
            }
        }
    }
}
