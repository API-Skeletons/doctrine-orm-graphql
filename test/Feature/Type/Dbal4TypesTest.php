<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\EntityDbal4\Dbal4Types;
use BcMath\Number;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function dirname;
use function method_exists;

/**
 * The Doctrine types added in DBAL 4
 */
class Dbal4TypesTest extends TestCase
{
    private const array TYPES = [
        'smallfloat',
        'number',
        'enum',
        'json_object',
        'jsonb',
        'jsonb_object',
        'datetime_utc',
        'datetime_utc_immutable',
    ];

    private Schema $schema;

    public function setUp(): void
    {
        foreach (self::TYPES as $type) {
            if (Type::hasType($type)) {
                continue;
            }

            $this->markTestSkipped('DBAL has no ' . $type . ' type');
        }

        if (! class_exists(Number::class)) {
            $this->markTestSkipped('The number type requires the bcmath extension');
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2) . '/EntityDbal4'],
            isDevMode: true,
        );
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $em->persist(new Dbal4Types(
            1.5,
            new Number('12.5'),
            'small',
            (object) ['a' => 1],
            ['b' => 2],
            (object) ['c' => 3],
            new DateTime('2004-02-12 15:19:21', new DateTimeZone('+05:00')),
            new DateTimeImmutable('2004-02-12 15:19:21', new DateTimeZone('+05:00')),
        ));
        $em->flush();
        $em->clear();

        $driver       = new Driver($em);
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['dbal4Types' => $driver->completeConnection(Dbal4Types::class)],
            ]),
        ]);
    }

    /** @return mixed[] */
    private function execute(string $query): array
    {
        $result = GraphQL::executeQuery($this->schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data']['dbal4Types']['edges'];
    }

    public function testValuesAreSerialized(): void
    {
        $edges = $this->execute('{ dbal4Types { edges { node { smallFloat number enum jsonObject jsonb jsonbObject '
            . 'dateTimeUtc dateTimeUtcImmutable } } } }');

        $this->assertSame(
            [
                'smallFloat' => 1.5,
                // SQLite returns a decimal as a float, which drops its trailing zeros
                'number' => '12.5',
                'enum' => 'small',
                'jsonObject' => '{"a":1}',
                'jsonb' => '{"b":2}',
                'jsonbObject' => '{"c":3}',
                'dateTimeUtc' => '2004-02-12T10:19:21+00:00',
                'dateTimeUtcImmutable' => '2004-02-12T10:19:21+00:00',
            ],
            $edges[0]['node'],
        );
    }

    /**
     * A UTC date and time is compared in UTC whatever the offset of the value
     */
    public function testUtcFilters(): void
    {
        foreach (['dateTimeUtc', 'dateTimeUtcImmutable'] as $field) {
            foreach (['2004-02-12T10:19:21+00:00', '2004-02-12T15:19:21+05:00'] as $value) {
                $this->assertCount(
                    1,
                    $this->execute('{ dbal4Types(filter: { ' . $field . ': { eq: "' . $value . '" } }) { edges { node { id } } } }'),
                    $field . ' ' . $value,
                );
            }
        }
    }

    public function testFilters(): void
    {
        $this->assertCount(1, $this->execute('{ dbal4Types(filter: { smallFloat: { gt: 1 } }) { edges { node { id } } } }'));
        $this->assertCount(1, $this->execute('{ dbal4Types(filter: { enum: { eq: "small" } }) { edges { node { id } } } }'));
        $this->assertCount(0, $this->execute('{ dbal4Types(filter: { enum: { eq: "large" } }) { edges { node { id } } } }'));
        $this->assertCount(1, $this->execute('{ dbal4Types(filter: { number: { eq: "12.5" } }) { edges { node { id } } } }'));
        $this->assertCount(1, $this->execute('{ dbal4Types(filter: { number: { gt: "12" } }) { edges { node { id } } } }'));
        $this->assertCount(0, $this->execute('{ dbal4Types(filter: { number: { lt: "12.5" } }) { edges { node { id } } } }'));

        $result = GraphQL::executeQuery(
            $this->schema,
            '{ dbal4Types(filter: { number: { contains: "2" } }) { edges { node { id } } } }',
        )->toArray();
        $this->assertStringContainsString('Field "contains" is not defined', $result['errors'][0]['message']);

        $result = GraphQL::executeQuery(
            $this->schema,
            '{ dbal4Types(filter: { number: { gt: "twelve" } }) { edges { node { id } } } }',
        )->toArray();
        $this->assertSame("Filter 'gt' of field 'number' must be a number.", $result['errors'][0]['message']);
    }
}
