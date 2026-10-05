<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Input as InputException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEmbeddableAddress;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestEntityWithEmbedded;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_keys;

/**
 * An entity with an embeddable is exposed without the embeddable's fields,
 * which Doctrine names <property>.<field>
 */
class EmbeddedTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->getEntityManager()->persist(
            new TestEntityWithEmbedded('Office', new TestEmbeddableAddress('1 Main Street', 'Springfield')),
        );
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
    }

    /** @return array<string, array{bool}> */
    public static function extractionProvider(): array
    {
        return [
            'by value' => [true],
            'by reference' => [false],
        ];
    }

    #[DataProvider('extractionProvider')]
    public function testEntityWithEmbeddableIsExposed(bool $extractByValue): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'Embedded',
            'extractByValue' => $extractByValue,
        ]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['entities' => $driver->completeConnection(TestEntityWithEmbedded::class)],
            ]),
        ]);
        $schema->assertValid();

        $result = GraphQL::executeQuery(
            $schema,
            '{ entities(filter: { name: { eq: "Office" } }) { edges { node { id name city } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame(
            ['name' => 'Office', 'city' => 'Springfield'],
            [
                'name' => $result['data']['entities']['edges'][0]['node']['name'],
                'city' => $result['data']['entities']['edges'][0]['node']['city'],
            ],
        );
    }

    public function testEmbeddedFieldsAreNotExposed(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'Embedded']));

        $metadata = $driver->get(Metadata::class)[TestEntityWithEmbedded::class];
        $this->assertSame(['id', 'name'], array_keys($metadata['fields']));

        $objectType = $driver->type(TestEntityWithEmbedded::class);
        $this->assertInstanceOf(ObjectType::class, $objectType);
        $this->assertSame(['id', 'name', 'city'], array_keys($objectType->getFields()));

        $this->assertSame(['id', 'name', '_or'], array_keys($driver->filter(TestEntityWithEmbedded::class)->getFields()));
        $this->assertSame(['name'], array_keys($driver->input(TestEntityWithEmbedded::class)->getFields()));
    }

    public function testEmbeddedFieldIsNotInput(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'Embedded']));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage(
            'Field address.street is not exposed for entity ' . TestEntityWithEmbedded::class
            . ' in group Embedded and cannot be used as input.',
        );

        $driver->input(TestEntityWithEmbedded::class, ['address.street'])->getFields();
    }
}
