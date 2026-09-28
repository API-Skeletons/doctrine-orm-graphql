<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Configuration as ConfigurationException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Album;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

class ExtractByValueTest extends TestCase
{
    #[IgnoreDeprecations]
    public function testExtractByValueFalse(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['extractByValue' => false]));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artist' => [
                        'type' => $driver->connection(Artist::class),
                        'args' => [
                            'filter' => $driver->filter(Artist::class),
                        ],
                        'resolve' => $driver->resolve(Artist::class),
                    ],
                ],
            ]),
        ]);

        $query  = '{ artist { edges { node { performances ( filter: {venue: { neq: "test" } } ) { edges { node { venue } } } } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertFalse($driver->get(Config::class)->getExtractByValue());
    }

    /**
     * extractByValue overrides the byValue of every entity's attribute.  Album
     * is extracted by reference in this group.
     */
    public function testExtractByValueOverridesTheEntityAttribute(): void
    {
        $group = 'MappedSuperclassByReferenceTest';

        $byAttribute = (new Driver($this->getEntityManager(), new Config(['group' => $group])))
            ->get(Metadata::class)[Album::class]['byValue'];
        $byValue     = (new Driver($this->getEntityManager(), new Config(['group' => $group, 'extractByValue' => true])))
            ->get(Metadata::class)[Album::class]['byValue'];
        $byReference = (new Driver($this->getEntityManager(), new Config(['group' => 'default', 'extractByValue' => false])))
            ->get(Metadata::class)[Artist::class]['byValue'];

        $this->assertFalse($byAttribute);
        $this->assertTrue($byValue);
        $this->assertFalse($byReference);
    }

    public function testTheFormerNameIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration setting: globalByValue');

        new Config(['globalByValue' => true]);
    }
}
