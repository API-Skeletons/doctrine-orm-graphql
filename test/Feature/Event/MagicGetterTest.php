<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Traits\MagicGetter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use BadMethodCallException;

class MagicGetterTest extends AbstractTest
{
    public function testCannotCallInvalidProperty(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method getInvalidProperty does not exist');

        $class = new class {
            use MagicGetter;
        };

        $class->getInvalidProperty();
    }
}
