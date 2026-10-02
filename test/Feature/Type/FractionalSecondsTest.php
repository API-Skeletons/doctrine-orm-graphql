<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTime;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZ;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZImmutable;
use DateTime as PHPDateTime;
use DateTimeImmutable as PHPDateTimeImmutable;
use DateTimeInterface;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function assert;

/**
 * The date and time scalars take a fraction of a second, as JavaScript's
 * Date.toISOString() gives, and serialize one when a value has one
 */
class FractionalSecondsTest extends TestCase
{
    /** @return array<string, array{class-string<ScalarType>}> */
    public static function scalarProvider(): array
    {
        return [
            'DateTime' => [DateTime::class],
            'DateTimeImmutable' => [DateTimeImmutable::class],
            'DateTimeTZ' => [DateTimeTZ::class],
            'DateTimeTZImmutable' => [DateTimeTZImmutable::class],
        ];
    }

    /** @return array<string, array{class-string<ScalarType>, string, string}> */
    public static function fractionProvider(): array
    {
        $fractions = [
            'one digit' => ['2004-02-12T15:19:21.1+00:00', '100000'],
            'milliseconds in UTC' => ['2004-02-12T15:19:21.123Z', '123000'],
            'microseconds' => ['2004-02-12T15:19:21.123456+00:00', '123456'],
            'beyond microseconds, truncated' => ['2004-02-12T15:19:21.1234567Z', '123456'],
        ];

        $cases = [];
        foreach (self::scalarProvider() as $scalar => [$class]) {
            foreach ($fractions as $name => [$value, $microseconds]) {
                $cases[$scalar . ', ' . $name] = [$class, $value, $microseconds];
            }
        }

        return $cases;
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('fractionProvider')]
    public function testFractionIsParsed(string $class, string $value, string $microseconds): void
    {
        $scalar = new $class();

        $parsed = $scalar->parseValue($value);
        assert($parsed instanceof DateTimeInterface);
        $this->assertSame($microseconds, $parsed->format('u'));
        $this->assertSame((new PHPDateTimeImmutable('2004-02-12T15:19:21Z'))->getTimestamp(), $parsed->getTimestamp());

        $literal = $scalar->parseLiteral(new StringValueNode(['value' => $value]));
        assert($literal instanceof DateTimeInterface);
        $this->assertSame($microseconds, $literal->format('u'));
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('scalarProvider')]
    public function testOffsetIsKept(string $class): void
    {
        $parsed = (new $class())->parseValue('2004-02-12T15:19:21.5+05:30');
        assert($parsed instanceof DateTimeInterface);

        $this->assertSame(
            (new PHPDateTimeImmutable('2004-02-12T09:49:21Z'))->getTimestamp(),
            $parsed->getTimestamp(),
        );
        $this->assertSame('500000', $parsed->format('u'));
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('scalarProvider')]
    public function testValueOfWholeSecondsIsSerializedWithoutAFraction(string $class): void
    {
        $this->assertSame(
            '2004-02-12T15:19:21+00:00',
            (new $class())->serialize(new PHPDateTime('2004-02-12T15:19:21+00:00')),
        );
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('scalarProvider')]
    public function testValueWithAFractionIsSerializedWithIt(string $class): void
    {
        $this->assertSame(
            '2004-02-12T15:19:21.123000+00:00',
            (new $class())->serialize(new PHPDateTimeImmutable('2004-02-12T15:19:21.123+00:00')),
        );
    }

    /**
     * A serialized value is parsed to the same instant
     *
     * @param class-string<ScalarType> $class
     */
    #[DataProvider('scalarProvider')]
    public function testFractionRoundTrips(string $class): void
    {
        $scalar = new $class();
        $value  = new PHPDateTimeImmutable('2004-02-12T15:19:21.654321+00:00');

        $parsed = $scalar->parseValue($scalar->serialize($value));
        assert($parsed instanceof DateTimeInterface);

        $this->assertSame($value->format('U.u'), $parsed->format('U.u'));
    }

    /** @return array<string, array{class-string<ScalarType>, string}> */
    public static function invalidProvider(): array
    {
        $values = [
            'a point without digits' => '2004-02-12T15:19:21.Z',
            'a fraction without an offset' => '2004-02-12T15:19:21.123',
            'a fraction which is not digits' => '2004-02-12T15:19:21.12a+00:00',
            'a fraction without seconds' => '2004-02-12T15:19.123+00:00',
        ];

        $cases = [];
        foreach (self::scalarProvider() as $scalar => [$class]) {
            foreach ($values as $name => $value) {
                $cases[$scalar . ', ' . $name] = [$class, $value];
            }
        }

        return $cases;
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('invalidProvider')]
    public function testMalformedFractionIsRejected(string $class, string $value): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('format does not match ISO 8601');

        (new $class())->parseValue($value);
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('scalarProvider')]
    public function testImpossibleDateWithAFractionIsRejected(string $class): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('is not a valid date or time');

        (new $class())->parseValue('2004-02-31T15:19:21.123Z');
    }
}
