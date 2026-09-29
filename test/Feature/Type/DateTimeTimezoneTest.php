<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Date;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTime;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeImmutable;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\DateTimeTZ;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use DateTimeInterface;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function date_default_timezone_get;
use function date_default_timezone_set;

/**
 * A datetime column stores the date and time without an offset, and Doctrine
 * reads it in the default timezone, so a date-time given with another offset
 * is converted to the default timezone, the same instant.  A date is not
 * converted.
 */
class DateTimeTimezoneTest extends TestCase
{
    private string $timezone;

    public function setUp(): void
    {
        parent::setUp();

        $this->timezone = date_default_timezone_get();
    }

    public function tearDown(): void
    {
        date_default_timezone_set($this->timezone);

        parent::tearDown();
    }

    /** @return array<string, array{class-string<ScalarType>}> */
    public static function scalarProvider(): array
    {
        return [
            'DateTime' => [DateTime::class],
            'DateTimeImmutable' => [DateTimeImmutable::class],
        ];
    }

    /** @param class-string<ScalarType> $class */
    #[DataProvider('scalarProvider')]
    public function testValueIsInTheDefaultTimezone(string $class): void
    {
        date_default_timezone_set('America/New_York');

        $value = (new $class())->parseValue('2004-02-12T15:19:21+05:00');

        $this->assertInstanceOf(DateTimeInterface::class, $value);
        $this->assertSame('America/New_York', $value->getTimezone()->getName());
        // 10:19:21 UTC
        $this->assertSame('2004-02-12T05:19:21-05:00', $value->format(DateTimeInterface::ATOM));
    }

    /**
     * A DateTimeTZ keeps its offset, for a column which stores one
     */
    public function testDateTimeTZKeepsItsOffset(): void
    {
        $value = (new DateTimeTZ())->parseValue('2004-02-12T15:19:21+05:00');

        $this->assertSame('+05:00', $value->getTimezone()->getName());
    }

    /**
     * The stored performance date is 1995-02-21T00:00:00+00:00 in UTC
     *
     * @return array<string, array{string}>
     */
    public static function filterProvider(): array
    {
        return [
            'the same offset' => ['eq: "1995-02-21T00:00:00+00:00"'],
            'another offset' => ['eq: "1995-02-21T05:00:00+05:00"'],
            'a negative offset' => ['eq: "1995-02-20T19:00:00-05:00"'],
            'between' => ['between: { from: "1995-02-21T04:00:00+05:00", to: "1995-02-21T06:00:00+05:00" }'],
        ];
    }

    #[DataProvider('filterProvider')]
    public function testFilterMatchesTheInstant(string $filter): void
    {
        $driver = new Driver($this->getEntityManager());
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['performance' => $driver->completeConnection(Performance::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ performance(filter: { performanceDate: { ' . $filter . ' } }) { edges { node { id } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame([['node' => ['id' => 1]]], $result['data']['performance']['edges']);
    }

    /**
     * A value from input is stored as the same instant
     */
    public function testInputIsStoredAsTheInstant(): void
    {
        $performance = $this->getEntityManager()->getRepository(Performance::class)->find(1);
        $this->assertInstanceOf(Performance::class, $performance);

        $performance->setPerformanceDate((new DateTime())->parseValue('2004-02-12T15:19:21+05:00'));
        $this->getEntityManager()->flush();

        $this->assertSame(
            '2004-02-12 10:19:21',
            $this->getEntityManager()->getConnection()->fetchOne('SELECT performanceDate FROM performance WHERE id = 1'),
        );
    }

    /**
     * Timezones from UTC-11 to UTC+14
     *
     * @return array<string, array{string}>
     */
    public static function timezoneProvider(): array
    {
        return [
            'UTC' => ['UTC'],
            'UTC-11' => ['Pacific/Pago_Pago'],
            'UTC-5' => ['America/New_York'],
            'UTC+5:30' => ['Asia/Kolkata'],
            'UTC+14' => ['Pacific/Kiritimati'],
        ];
    }

    /**
     * A date has no instant, so it is not converted to the default timezone,
     * which could make it another day.  In any default timezone, a date is
     * stored, filtered by and returned as the date the client sent.
     */
    #[DataProvider('timezoneProvider')]
    public function testDateIsTheSameDayInEveryTimezone(string $timezone): void
    {
        date_default_timezone_set($timezone);

        $entityManager = $this->getEntityManager();
        $typeTest      = $entityManager->getRepository(TypeTest::class)->findOneBy([]);
        $this->assertInstanceOf(TypeTest::class, $typeTest);

        $typeTest->setTestDate((new Date())->parseValue('2004-02-12'));
        $typeTest->setTestDateImmutable((new DateImmutable())->parseValue('2004-02-12'));
        $entityManager->flush();
        $entityManager->clear();

        $this->assertSame(
            ['testDate' => '2004-02-12', 'testDateImmutable' => '2004-02-12'],
            $entityManager->getConnection()->fetchAssociative('SELECT testDate, testDateImmutable FROM typetest'),
        );

        $driver = new Driver($entityManager, new Config(['group' => 'DataTypesTest']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['typeTest' => $driver->completeConnection(TypeTest::class)],
            ]),
        ]);

        $filters = [
            'eq: "2004-02-12"' => true,
            'neq: "2004-02-12"' => false,
            'lt: "2004-02-13"' => true,
            'lt: "2004-02-12"' => false,
            'lte: "2004-02-12"' => true,
            'gt: "2004-02-11"' => true,
            'gt: "2004-02-12"' => false,
            'gte: "2004-02-12"' => true,
            'between: { from: "2004-02-11", to: "2004-02-12" }' => true,
            'between: { from: "2004-02-13", to: "2004-02-14" }' => false,
            'in: ["2004-02-12"]' => true,
            'notin: ["2004-02-12"]' => false,
        ];

        foreach (['testDate', 'testDateImmutable'] as $field) {
            foreach ($filters as $filter => $matches) {
                $result = GraphQL::executeQuery(
                    $schema,
                    '{ typeTest(filter: { ' . $field . ': { ' . $filter . ' } }) { edges { node { ' . $field . ' } } } }',
                )->toArray();

                $this->assertArrayNotHasKey('errors', $result);
                $this->assertSame(
                    $matches ? [['node' => [$field => '2004-02-12']]] : [],
                    $result['data']['typeTest']['edges'],
                    $timezone . ' ' . $field . ' ' . $filter,
                );
            }
        }
    }
}
