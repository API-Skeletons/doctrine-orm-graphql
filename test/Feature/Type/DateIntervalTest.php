<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateInterval;
use DateInterval as PHPDateInterval;
use DateTime;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateIntervalTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function validProvider(): array
    {
        // value => serialized
        return [
            'every part' => ['P1Y2M3DT4H5M6S', 'P1Y2M3DT4H5M6S'],
            'date only' => ['P1D', 'P1D'],
            'time only' => ['PT30M', 'PT30M'],
            'negative' => ['-P1D', '-P1D'],
            'positive sign' => ['+P1D', 'P1D'],
            'weeks' => ['P2W', 'P14D'],
            'zero' => ['PT0S', 'PT0S'],
            'negative zero' => ['-P0D', 'PT0S'],
            'zero parts' => ['P0Y1M', 'P1M'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testParseAndSerialize(string $value, string $serialized): void
    {
        $type = new DateInterval();

        $interval = $type->parseValue($value);
        $this->assertSame($serialized, $type->serialize($interval));

        $literal = $type->parseLiteral(new StringValueNode(['value' => $value]));
        $this->assertEquals($interval, $literal);
    }

    public function testNegativeIsInverted(): void
    {
        $this->assertSame(1, (new DateInterval())->parseValue('-P1D')->invert);
        $this->assertSame(0, (new DateInterval())->parseValue('P1D')->invert);
    }

    public function testDifferenceIsSerialized(): void
    {
        $interval = (new DateTime('2004-03-01'))->diff(new DateTime('2004-01-01'));

        $this->assertSame('-P2M', (new DateInterval())->serialize($interval));
    }

    /** @return array<string, array{string}> */
    public static function invalidProvider(): array
    {
        return [
            'empty' => [''],
            'no parts' => ['P'],
            'no time parts' => ['P1DT'],
            'no designator' => ['1D'],
            'no unit' => ['P1'],
            'fraction' => ['PT1.5S'],
            'wrong order' => ['P1D1Y'],
            'text' => ['one day'],
            'too large' => ['P99999999999999999999Y'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidValueIsRejected(string $value): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('does not match ISO 8601');

        (new DateInterval())->parseValue($value);
    }

    public function testNonStringValueIsRejected(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('DateInterval is not a string: int');

        (new DateInterval())->parseValue(1);
    }

    public function testNonStringLiteralIsRejected(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Can only parse strings got: IntValue');

        (new DateInterval())->parseLiteral(new IntValueNode(['value' => '1']));
    }

    public function testSerializeRejectsNonInterval(): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Expected a DateInterval for DateInterval.  Got string.');

        (new DateInterval())->serialize('P1D');
    }

    public function testSerializeAcceptsPHPDateInterval(): void
    {
        $this->assertSame('PT1H', (new DateInterval())->serialize(new PHPDateInterval('PT1H')));
    }
}
