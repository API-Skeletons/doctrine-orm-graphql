<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as FilterQueryBuilder;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestIntegerRanges;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function method_exists;

/**
 * An integer filter value must be within its column's range, which is a
 * database error beyond it on some databases, such as PostgreSQL
 */
class IntegerRangeFilterTest extends TestCase
{
    /** @return mixed[] */
    private function execute(string $filter): array
    {
        $this->getEntityManager()->persist(new TestIntegerRanges());
        $this->getEntityManager()->flush();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'IntegerRanges']));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestIntegerRanges::class)],
            ]),
        ]);

        return GraphQL::executeQuery($schema, '{ entities(filter: { ' . $filter . ' }) { edges { node { id } } } }')
            ->toArray();
    }

    /** @return array<string, array{string, string}> */
    public static function outOfRangeProvider(): array
    {
        return [
            'smallint' => [
                'small: { eq: 40000 }',
                "Filter 'eq' of field 'small' must be an integer from -32768 to 32767.",
            ],
            'smallint in a list' => [
                'small: { in: [1, -32769] }',
                "Filter 'in' of field 'small' must be an integer from -32768 to 32767.",
            ],
            'smallint between' => [
                'small: { between: { from: 1, to: 32768 } }',
                "Filter 'between' of field 'small' must be an integer from -32768 to 32767.",
            ],
            'bigint' => [
                'big: { gt: "9223372036854775808" }',
                "Filter 'gt' of field 'big' must be an integer from -9223372036854775808 to 9223372036854775807.",
            ],
            'association to an integer identifier' => [
                'parent: { eq: "2147483648" }',
                "Filter 'eq' of field 'parent' must be an integer from -2147483648 to 2147483647.",
            ],
            // The unsigned option is ignored but by MySQL
            'unsigned bigint on SQLite' => [
                'unsignedBig: { eq: "9223372036854775808" }',
                "Filter 'eq' of field 'unsignedBig' must be an integer from -9223372036854775808 to 9223372036854775807.",
            ],
        ];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testValueOutOfRangeIsRejected(string $filter, string $message): void
    {
        $this->assertSame($message, $this->execute($filter)['errors'][0]['message']);
    }

    /** @return array<string, array{string}> */
    public static function inRangeProvider(): array
    {
        return [
            'smallint' => ['small: { in: [-32768, 32767], between: { from: -32768, to: 32767 } }'],
            'bigint' => ['big: { in: ["-9223372036854775808", "9223372036854775807"] }'],
            'leading zeros' => ['big: { eq: "-000009223372036854775808" }'],
            'association to an integer identifier' => ['parent: { in: ["-2147483648", "2147483647"] }'],
            'unsigned integer on SQLite' => ['unsignedInteger: { eq: -1 }'],
            'isnull' => ['small: { isnull: false }'],
        ];
    }

    #[DataProvider('inRangeProvider')]
    public function testValueInRangeIsAccepted(string $filter): void
    {
        $this->assertArrayNotHasKey('errors', $this->execute($filter));
    }

    /**
     * An entity manager for MySQL, which is not connected to: the filters are
     * checked as they are added to a query, before it is run
     */
    private function mysqlEntityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(paths: [__DIR__ . '/../../Entity'], isDevMode: true);
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        return new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.0.30'], $config),
            $config,
        );
    }

    /** @param array<string, array<string, mixed>> $filters */
    private function applyOnMysql(array $filters): void
    {
        $entityManager = $this->mysqlEntityManager();
        $driver        = new Driver($entityManager, new Config(['group' => 'IntegerRanges']));
        $entity        = $driver->get(EntityTypeContainer::class)->get(TestIntegerRanges::class);
        $queryBuilder  = $entityManager->createQueryBuilder()->select('entity')->from(TestIntegerRanges::class, 'entity');

        (new FilterQueryBuilder())->apply($filters, $queryBuilder, $entity);
    }

    /** @return array<string, array{array<string, array<string, mixed>>, string}> */
    public static function mysqlOutOfRangeProvider(): array
    {
        return [
            'unsigned integer' => [
                ['unsignedInteger' => ['eq' => -1]],
                "Filter 'eq' of field 'unsignedInteger' must be an integer from 0 to 4294967295.",
            ],
            'unsigned bigint' => [
                ['unsignedBig' => ['eq' => '18446744073709551616']],
                "Filter 'eq' of field 'unsignedBig' must be an integer from 0 to 18446744073709551615.",
            ],
            'signed smallint' => [
                ['small' => ['eq' => 40000]],
                "Filter 'eq' of field 'small' must be an integer from -32768 to 32767.",
            ],
        ];
    }

    /** @param array<string, array<string, mixed>> $filters */
    #[DataProvider('mysqlOutOfRangeProvider')]
    public function testMysqlUnsignedRange(array $filters, string $message): void
    {
        $this->expectException(FilterException::class);
        $this->expectExceptionMessage($message);

        $this->applyOnMysql($filters);
    }

    public function testMysqlUnsignedValuesBeyondTheSignedRangeAreAccepted(): void
    {
        $this->applyOnMysql([
            'unsignedInteger' => ['eq' => 4294967295],
            'unsignedBig' => ['in' => ['0', '18446744073709551615']],
        ]);

        $this->addToAssertionCount(1);
    }
}
