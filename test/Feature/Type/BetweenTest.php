<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Between;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Field;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

class BetweenTest extends TestCase
{
    /**
     * Two fields of the same type with different filters have different
     * filter types, which share the Between type from the TypeContainer
     */
    public function testTwoFilterSetsEachWithBetweenButDifferentOtherwiseFetchesBetweenFromTypeContainer(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'BetweenTypeContainerTest']));
        $filter = $driver->filter(TypeTest::class);

        $testInt      = $filter->getField('testInt')->getType();
        $testSmallInt = $filter->getField('testSmallInt')->getType();
        $this->assertInstanceOf(Field::class, $testInt);
        $this->assertInstanceOf(Field::class, $testSmallInt);
        $this->assertNotSame($testInt, $testSmallInt);

        $between = $testInt->getField('between')->getType();
        $this->assertInstanceOf(Between::class, $between);
        $this->assertSame('Between_Int', $between->name());
        $this->assertSame($between, $testSmallInt->getField('between')->getType());
    }
}
