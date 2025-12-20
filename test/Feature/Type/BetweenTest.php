<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class BetweenTest extends TestCase
{
    public function testTwoFilterSetsEachWithBetweenButDifferentOtherwiseFetchesBetweenFromTypeContainer(): void
    {
        $config = new Config(['group' => 'BetweenTypeContainerTest']);

        $driver = new Driver($this->getEntityManager(), $config);

        $schema1 = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typetest' => $driver->completeConnection(TypeTest::class),
                ],
            ]),
        ]);

        $this->assertTrue(true);
    }
}
