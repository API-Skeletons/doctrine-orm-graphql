<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Album;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Fields and associations declared as private properties on a mapped
 * superclass are exposed on the child entity, whether the child is
 * extracted by value or by reference.
 */
class MappedSuperclassTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function groupProvider(): array
    {
        return [
            'by value' => ['MappedSuperclassTest'],
            'by reference' => ['MappedSuperclassByReferenceTest'],
        ];
    }

    #[DataProvider('groupProvider')]
    public function testInheritedPrivatePropertiesAreExposed(string $group): void
    {
        $artist = $this->getEntityManager()->getRepository(Artist::class)
            ->findOneBy(['name' => 'Grateful Dead']);

        $album = (new Album())
            ->setTitle('American Beauty')
            ->setTrackCount(10)
            ->setArtist($artist);
        $this->getEntityManager()->persist($album);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $albumType = $driver->type(Album::class);
        $this->assertTrue($albumType->hasField('id'));
        $this->assertTrue($albumType->hasField('title'));
        $this->assertTrue($albumType->hasField('trackCount'));
        $this->assertTrue($albumType->hasField('artist'));

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'album' => $driver->completeConnection(Album::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ album { edges { node { id title trackCount artist { name } } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $node = $result['data']['album']['edges'][0]['node'];

        $this->assertSame($album->getId(), $node['id']);
        $this->assertSame('American Beauty', $node['title']);
        $this->assertSame(10, $node['trackCount']);
        $this->assertSame('Grateful Dead', $node['artist']['name']);
    }
}
