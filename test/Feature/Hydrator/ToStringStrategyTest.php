<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToString;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

/**
 * The ToString strategy is registered by default and extracts an integer
 * column as a native string.  The hydrator output is checked directly
 * because the GraphQL String type would also coerce an integer.
 */
class ToStringStrategyTest extends TestCase
{
    public function testIntegerFieldIsExtractedAsString(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ToStringStrategy']));

        $metadata = $driver->get(EntityTypeContainer::class)->get(TypeTest::class)->getMetadata();
        $this->assertSame(ToString::class, $metadata['fields']['testInt']['hydratorStrategy']);

        $typeTest = $this->getEntityManager()->getRepository(TypeTest::class)->find(1);
        $data     = $driver->get(HydratorContainer::class)->get(TypeTest::class)->extract($typeTest);

        $this->assertSame((string) $typeTest->getTestInt(), $data['testInt']);
    }
}
