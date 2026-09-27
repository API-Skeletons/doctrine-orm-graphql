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
}
