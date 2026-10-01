<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestNonNullTypes;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestNonNullTypesDetail;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

use function array_keys;

/**
 * Doctrine cannot compare the inverse side of a one-to-one association,
 * which has no column, so it has no filter.  The owning side has.
 */
class InverseOneToOneFilterTest extends TestCase
{
    private function driver(): Driver
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'NonNullTypes']));
        $driver->get(TypeContainer::class)
            ->set('requiredstring', static fn () => Type::nonNull(Type::string()));

        return $driver;
    }

    public function testInverseSideHasNoFilter(): void
    {
        $driver = $this->driver();

        $this->assertSame(
            ['id', 'name', 'nickname', 'requiredArtist', 'optionalArtist', 'defaultArtist'],
            array_keys($driver->filter(TestNonNullTypes::class)->getFields()),
        );
        $this->assertSame(['id', 'owner'], array_keys($driver->filter(TestNonNullTypesDetail::class)->getFields()));
    }

    public function testOwningSideIsFiltered(): void
    {
        $artist = $this->getEntityManager()->getRepository(Artist::class)->findOneBy(['name' => 'Grateful Dead']);
        $this->assertInstanceOf(Artist::class, $artist);

        $first  = new TestNonNullTypes($artist);
        $second = new TestNonNullTypes($artist);
        $this->getEntityManager()->persist($first);
        $this->getEntityManager()->persist($second);
        $this->getEntityManager()->persist(new TestNonNullTypesDetail($first));
        $this->getEntityManager()->persist(new TestNonNullTypesDetail($second));
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $driver = $this->driver();
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['details' => $driver->completeConnection(TestNonNullTypesDetail::class)],
            ]),
        ]);

        $result = GraphQL::executeQuery(
            $schema,
            '{ details(filter: { owner: { eq: ' . $second->getId() . ' } }) { edges { node { owner { id } } } } }',
        )->toArray();

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame([['node' => ['owner' => ['id' => $second->getId()]]]], $result['data']['details']['edges']);
    }
}
