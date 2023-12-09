<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTime as DateTimeType;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use DateTime;
use GraphQL\Error\Error;

class DateTimeTest extends AbstractTest
{
    public function testParseValue(): void
    {
        $dateTimeType = new DateTimeType();
        $control      = DateTime::createFromFormat('Y-m-d\TH:i:sP', '2020-03-01T00:00:00+00:00');
        $result       = $dateTimeType->parseValue('2020-03-01T00:00:00+00:00');

        $this->assertEquals($control, $result);
    }

    public function testParseValueNull(): void
    {
        $this->expectException(Error::class);

        $dateTimeType = new DateTimeType();
        $result       = $dateTimeType->parseValue(null);
    }

    public function testParseValueInvalid(): void
    {
        $this->expectException(Error::class);

        $dateType = new DateTimeType();
        $result   = $dateType->parseValue('03/01/2020');
    }
}
