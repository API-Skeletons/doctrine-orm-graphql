<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\DbalTypes;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use DateInterval;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

use function array_keys;
use function base64_encode;

/**
 * The ascii_string, guid, binary and dateinterval Doctrine types
 */
class DbalTypesTest extends TestCase
{
    private const string GUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    private Driver $driver;

    private Schema $schema;

    public function setUp(): void
    {
        parent::setUp();

        $interval         = new DateInterval('P1D');
        $interval->invert = 1;

        $em = $this->getEntityManager();
        $em->persist(new DbalTypes('ascii', self::GUID, "\x00\x01binary", new DateInterval('P1Y2M3DT4H5M6S')));
        $em->persist(new DbalTypes('other', 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11', 'other', $interval));
        $em->flush();
        $em->clear();

        $this->driver = new Driver($em, new Config(['group' => 'DbalTypes']));
        $this->schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['dbalTypes' => $this->driver->completeConnection(DbalTypes::class)],
            ]),
        ]);
    }

    /** @return mixed[] */
    private function execute(string $query): array
    {
        $result = GraphQL::executeQuery($this->schema, $query)->toArray();
        $this->assertArrayNotHasKey('errors', $result);

        return $result['data']['dbalTypes']['edges'];
    }

    public function testValuesAreSerialized(): void
    {
        $edges = $this->execute('{ dbalTypes { edges { node { asciiString guid binary dateInterval } } } }');

        $this->assertSame(
            [
                [
                    'node' => [
                        'asciiString' => 'ascii',
                        'guid' => self::GUID,
                        'binary' => base64_encode("\x00\x01binary"),
                        'dateInterval' => 'P1Y2M3DT4H5M6S',
                    ],
                ],
                [
                    'node' => [
                        'asciiString' => 'other',
                        'guid' => 'b0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                        'binary' => base64_encode('other'),
                        'dateInterval' => '-P1D',
                    ],
                ],
            ],
            $edges,
        );
    }

    public function testFilters(): void
    {
        $this->assertCount(1, $this->execute('{ dbalTypes(filter: { guid: { eq: "' . self::GUID . '" } }) { edges { node { id } } } }'));
        $this->assertCount(1, $this->execute('{ dbalTypes(filter: { asciiString: { startswith: "asc" } }) { edges { node { id } } } }'));
        $this->assertCount(1, $this->execute('{ dbalTypes(filter: { dateInterval: { eq: "-P1D" } }) { edges { node { id } } } }'));
        $this->assertCount(
            2,
            $this->execute('{ dbalTypes(filter: { dateInterval: { in: ["-P1D", "P1Y2M3DT4H5M6S"] } }) { edges { node { id } } } }'),
        );
    }

    /**
     * An interval is stored as a string, which does not order as the duration
     */
    public function testDateIntervalFilters(): void
    {
        $filter = $this->driver->filter(DbalTypes::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);

        $dateInterval = $filter->getField('dateInterval')->getType();
        $this->assertInstanceOf(InputObjectType::class, $dateInterval);

        $this->assertSame(['eq', 'neq', 'in', 'notin', 'isnull'], array_keys($dateInterval->getFields()));
    }

    /**
     * Types which map to the same GraphQL type share it; a schema may have
     * only one type of each name
     */
    public function testAliasesShareTheirType(): void
    {
        $this->assertSame($this->driver->type('blob'), $this->driver->type('binary'));
        $this->assertSame($this->driver->type('json'), $this->driver->type('jsonb'));
        $this->assertSame($this->driver->type('json'), $this->driver->type('json_object'));
        $this->assertSame($this->driver->type('json'), $this->driver->type('jsonb_object'));
        $this->assertSame($this->driver->type('datetime'), $this->driver->type('datetime_utc'));
        $this->assertSame($this->driver->type('datetime_immutable'), $this->driver->type('datetime_utc_immutable'));
    }
}
