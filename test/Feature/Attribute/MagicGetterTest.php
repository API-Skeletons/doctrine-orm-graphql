<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Attribute;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute\Traits\MagicGetter;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use BadMethodCallException;

class MagicGetterTest extends AbstractTest
{
    public function testCannotCallGetIncludeFilters(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method getIncludeFilters does not exist');

        $class = new class {
            use MagicGetter;
        };

        $class->getIncludeFilters();
    }

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
