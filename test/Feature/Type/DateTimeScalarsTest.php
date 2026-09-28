<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\TypeSerialization as TypeSerializationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Date;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTime;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZ;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Time;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TimeImmutable;
use DateTime as PHPDateTime;
use DateTimeImmutable as PHPDateTimeImmutable;
use DateTimeInterface;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour shared by the eight date and time scalars
 */
class DateTimeScalarsTest extends TestCase
{
    /**
     * scalar class => [format of serialize, valid input, class parsed to]
     *
     * @return array<string, array{class-string<ScalarType>, string, string, class-string}>
     */
    public static function scalarProvider(): array
    {
        return [
            'Date' => [Date::class, 'Y-m-d', '2004-02-12', PHPDateTime::class],
            'DateImmutable' => [DateImmutable::class, 'Y-m-d', '2004-02-12', PHPDateTimeImmutable::class],
            'DateTime' => [DateTime::class, PHPDateTime::ATOM, '2004-02-12T15:19:21+00:00', PHPDateTime::class],
            'DateTimeImmutable' => [
                DateTimeImmutable::class,
                PHPDateTime::ATOM,
                '2004-02-12T15:19:21+00:00',
                PHPDateTimeImmutable::class,
            ],
            'DateTimeTZ' => [DateTimeTZ::class, PHPDateTime::ATOM, '2004-02-12T15:19:21+00:00', PHPDateTime::class],
            'DateTimeTZImmutable' => [
                DateTimeTZImmutable::class,
                PHPDateTime::ATOM,
                '2004-02-12T15:19:21+00:00',
                PHPDateTimeImmutable::class,
            ],
            'Time' => [Time::class, 'H:i:s.u', '15:19:21.123456', PHPDateTime::class],
            'TimeImmutable' => [TimeImmutable::class, 'H:i:s.u', '15:19:21.123456', PHPDateTimeImmutable::class],
        ];
    }

    /**
     * Either DateTimeInterface class is serialized; both format identically
     *
     * @param class-string<ScalarType> $scalarClass
     * @param class-string             $parsedClass
     */
    #[DataProvider('scalarProvider')]
    public function testSerializeAcceptsEitherDateTimeClass(
        string $scalarClass,
        string $format,
        string $validInput,
        string $parsedClass,
    ): void {
        $scalar  = new $scalarClass();
        $mutable = new PHPDateTime('2004-02-12 15:19:21.123456');

        $this->assertSame($mutable->format($format), $scalar->serialize($mutable));
        $this->assertSame(
            $mutable->format($format),
            $scalar->serialize(PHPDateTimeImmutable::createFromMutable($mutable)),
        );
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidOutputProvider(): array
    {
        return [
            'string' => ['2004-02-12', 'string'],
            'int' => [42, 'int'],
            'array' => [[], 'array'],
            'null' => [null, 'null'],
        ];
    }

    /**
     * Anything other than a DateTimeInterface is an error, not null or a
     * string passed through unchecked
     *
     * @param class-string<ScalarType> $scalarClass
     * @param class-string             $parsedClass
     */
    #[DataProvider('scalarProvider')]
    public function testSerializeRejectsAnythingElse(
        string $scalarClass,
        string $format,
        string $validInput,
        string $parsedClass,
    ): void {
        $scalar = new $scalarClass();

        foreach (self::invalidOutputProvider() as $description => [$value, $type]) {
            try {
                $scalar->serialize($value);
                $this->fail($scalarClass . ' serialized ' . $description);
            } catch (TypeSerializationException $e) {
                $this->assertSame(
                    'Expected a DateTimeInterface for ' . $scalar->name . '.  Got ' . $type . '.',
                    $e->getMessage(),
                );
            }
        }
    }

    /**
     * A literal is parsed and validated exactly as a variable is
     *
     * @param class-string<ScalarType> $scalarClass
     * @param class-string             $parsedClass
     */
    #[DataProvider('scalarProvider')]
    public function testParseLiteralParsesAsParseValue(
        string $scalarClass,
        string $format,
        string $validInput,
        string $parsedClass,
    ): void {
        $scalar      = new $scalarClass();
        $node        = new StringValueNode([]);
        $node->value = $validInput;

        $this->assertInstanceOf($parsedClass, $scalar->parseLiteral($node));
        $this->assertInstanceOf($parsedClass, $scalar->parseValue($validInput));

        $node->value = 'not a date';
        $this->expectException(TypeSerializationException::class);
        $scalar->parseLiteral($node);
    }

    /**
     * @param class-string<ScalarType> $scalarClass
     * @param class-string             $parsedClass
     */
    #[DataProvider('scalarProvider')]
    public function testParseLiteralRejectsANonStringLiteral(
        string $scalarClass,
        string $format,
        string $validInput,
        string $parsedClass,
    ): void {
        $node        = new IntValueNode([]);
        $node->value = '5';

        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('Query error: Can only parse strings got: IntValue');

        (new $scalarClass())->parseLiteral($node);
    }

    /**
     * A non-string is reported by its type, without an "Array to string
     * conversion" warning
     *
     *   * @param class-string<ScalarType> $scalarClass
     *
     * @param class-string<ScalarType> $scalarClass
     */
    #[DataProvider('scalarProvider')]
    public function testParseValueReportsTheTypeOfANonString(
        string $scalarClass,
        string $format,
        string $validInput,
        string $parsedClass,
    ): void {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage('is not a string: array');

        (new $scalarClass())->parseValue(['2004-02-12']);
    }

    /**
     * Dates and times which do not exist, and which PHP would roll over
     *
     * @return array<string, array{class-string<ScalarType>, string}>
     */
    public static function impossibleProvider(): array
    {
        $dates     = [
            'February 31' => '2004-02-31',
            'February 29 of a common year' => '2003-02-29',
            'April 31' => '2004-04-31',
        ];
        $dateTimes = [
            'February 31' => '2004-02-31T00:00:00+00:00',
            'month 13' => '2004-13-01T00:00:00+00:00',
            'hour 25' => '2004-02-12T25:00:00+00:00',
            'minute 61' => '2004-02-12T15:61:00+00:00',
            'second 61' => '2004-02-12T15:19:61+00:00',
        ];

        $cases = [];
        foreach ([Date::class, DateImmutable::class] as $class) {
            foreach ($dates as $name => $value) {
                $cases[$class . ', ' . $name] = [$class, $value];
            }
        }

        $dateTimeClasses = [DateTime::class, DateTimeImmutable::class, DateTimeTZ::class, DateTimeTZImmutable::class];
        foreach ($dateTimeClasses as $class) {
            foreach ($dateTimes as $name => $value) {
                $cases[$class . ', ' . $name] = [$class, $value];
            }
        }

        return $cases;
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('impossibleProvider')]
    public function testImpossibleValueIsRejected(string $class, string $value): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage($value . ' is not a valid date or time.');

        (new $class())->parseValue($value);
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('impossibleProvider')]
    public function testImpossibleLiteralIsRejected(string $class, string $value): void
    {
        $this->expectException(TypeSerializationException::class);
        $this->expectExceptionMessage($value . ' is not a valid date or time.');

        (new $class())->parseLiteral(new StringValueNode(['value' => $value]));
    }

    /**
     * February 29 of a leap year exists
     *
     * @return array<string, array{class-string<ScalarType>, string, string}>
     */
    public static function leapDayProvider(): array
    {
        return [
            'Date' => [Date::class, '2004-02-29', 'Y-m-d'],
            'DateImmutable' => [DateImmutable::class, '2004-02-29', 'Y-m-d'],
            'DateTime' => [DateTime::class, '2004-02-29T23:59:59+00:00', PHPDateTime::ATOM],
            'DateTimeImmutable' => [DateTimeImmutable::class, '2004-02-29T23:59:59+00:00', PHPDateTime::ATOM],
            'DateTimeTZ' => [DateTimeTZ::class, '2004-02-29T23:59:59+05:30', PHPDateTime::ATOM],
            'DateTimeTZImmutable' => [DateTimeTZImmutable::class, '2004-02-29T23:59:59+05:30', PHPDateTime::ATOM],
        ];
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('leapDayProvider')]
    public function testLeapDayIsAccepted(string $class, string $value, string $format): void
    {
        $parsed = (new $class())->parseValue($value);

        $this->assertInstanceOf(DateTimeInterface::class, $parsed);
        $this->assertSame($value, $parsed->format($format));
    }
}
