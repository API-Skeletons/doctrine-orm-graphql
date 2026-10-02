<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Security;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function array_map;

/**
 * A Doctrine SQL filter applies to every query the driver runs: connections,
 * collections, batched or not, their counts and the loading of to-one
 * associations.  The docs recommend one for row level security.
 */
class SqlFilterTest extends TestCase
{
    /** @return array<string, array{bool}> */
    public static function batchProvider(): array
    {
        return [
            'batched' => [true],
            'not batched' => [false],
        ];
    }

    #[DataProvider('batchProvider')]
    public function testSqlFilterAppliesToEveryQuery(bool $batchAssociations): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->getConfiguration()->addFilter('utahPhish', UtahPhishFilter::class);
        $entityManager->getFilters()->enable('utahPhish');
        $entityManager->clear();

        $driver = new Driver($entityManager, new Config(['batchAssociations' => $batchAssociations]));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'artists' => $driver->completeConnection(Artist::class),
                    'performances' => $driver->completeConnection(Performance::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{
            artists { totalCount edges { node { name performances { totalCount edges { node { venue } } } } } }
            performances { totalCount edges { node { venue artist { name } } } }
        }')->toArray();

        // The connection and its collection: Phish, and its performances in Utah
        $this->assertSame(1, $result['data']['artists']['totalCount']);
        $phish = $result['data']['artists']['edges'][0]['node'];
        $this->assertSame('Phish', $phish['name']);
        $this->assertSame(2, $phish['performances']['totalCount']);
        $this->assertSame(['E Center', 'E Center'], array_column(array_column($phish['performances']['edges'], 'node'), 'venue'));

        // The performances in Utah, of which one is of the Grateful Dead.  A
        // to-one association to a filtered row is null, with an error which
        // tells the client nothing of it.
        $this->assertSame(3, $result['data']['performances']['totalCount']);
        $this->assertSame([
            ['node' => ['venue' => 'Delta Center', 'artist' => ['name' => null]]],
            ['node' => ['venue' => 'E Center', 'artist' => ['name' => 'Phish']]],
            ['node' => ['venue' => 'E Center', 'artist' => ['name' => 'Phish']]],
        ], $result['data']['performances']['edges']);
        $this->assertSame([['message' => 'Internal server error', 'path' => ['performances', 'edges', 0, 'node', 'artist', 'name']]], array_map(
            static fn (array $error): array => ['message' => $error['message'], 'path' => $error['path']],
            $result['errors'],
        ));
    }
}
