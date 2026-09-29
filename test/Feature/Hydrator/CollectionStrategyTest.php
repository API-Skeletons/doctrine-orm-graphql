<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\DoctrineObjectWithComputed;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

/**
 * The hydrator sets the collection name and class metadata on a collection
 * strategy, so each collection has its own strategy
 */
class CollectionStrategyTest extends TestCase
{
    public function testEachCollectionHasItsOwnStrategy(): void
    {
        $hydratorContainer = (new Driver($this->getEntityManager()))->service(HydratorContainer::class);

        $artistHydrator = $hydratorContainer->get(Artist::class);
        $userHydrator   = $hydratorContainer->get(User::class);
        $this->assertInstanceOf(DoctrineObjectWithComputed::class, $artistHydrator);
        $this->assertInstanceOf(DoctrineObjectWithComputed::class, $userHydrator);

        $performances = $artistHydrator->getStrategy('performances');
        $recordings   = $userHydrator->getStrategy('recordings');
        $this->assertInstanceOf(AssociationDefault::class, $performances);
        $this->assertInstanceOf(AssociationDefault::class, $recordings);
        $this->assertNotSame($performances, $recordings);

        // Extracting sets each strategy's collection, which one entity's
        // extraction does not change for another's
        $artist = $this->getEntityManager()->getRepository(Artist::class)->findOneBy([]);
        $user   = $this->getEntityManager()->getRepository(User::class)->findOneBy([]);
        $this->assertInstanceOf(Artist::class, $artist);
        $this->assertInstanceOf(User::class, $user);

        $artistHydrator->extract($artist);
        $userHydrator->extract($user);

        $this->assertSame('performances', $performances->getCollectionName());
        $this->assertSame(Artist::class, $performances->getClassMetadata()->getName());
        $this->assertSame('recordings', $recordings->getCollectionName());
        $this->assertSame(User::class, $recordings->getClassMetadata()->getName());
    }
}
