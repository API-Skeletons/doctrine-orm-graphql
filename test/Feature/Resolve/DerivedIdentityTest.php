<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Resolve;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestDerivedAccount;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestDerivedBadge;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestDerivedMember;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TestDerivedTeam;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An entity of a derived identity has an association as its identifier.  A
 * collection of, or from, such entities is not batched.
 */
class DerivedIdentityTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->getEntityManager();

        foreach (['Red' => ['ann', 'bob'], 'Blue' => ['cat']] as $teamName => $accountNames) {
            $team = new TestDerivedTeam($teamName);
            $entityManager->persist($team);

            foreach ($accountNames as $accountName) {
                $account = new TestDerivedAccount($accountName);
                $member  = new TestDerivedMember($account, $team, 'player');
                $entityManager->persist($account);
                $entityManager->persist($member);
                $entityManager->persist(new TestDerivedBadge($accountName . ' badge', $member));
            }
        }

        $entityManager->flush();
        $entityManager->clear();
    }

    /** @return array<string, array{bool}> */
    public static function batchProvider(): array
    {
        return [
            'batched' => [true],
            'not batched' => [false],
        ];
    }

    #[DataProvider('batchProvider')]
    public function testQuery(bool $batchAssociations): void
    {
        $driver = new Driver($this->getEntityManager(), new Config([
            'group' => 'DerivedIdentity',
            'batchAssociations' => $batchAssociations,
        ]));
        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'teams' => $driver->completeConnection(TestDerivedTeam::class),
                    'badges' => $driver->completeConnection(TestDerivedBadge::class),
                ],
            ]),
        ]);

        $result = GraphQL::executeQuery($schema, '{
            teams { edges { node { name members { totalCount edges { node { account { name } badges { edges { node { name } } } } } } } } }
            badges { edges { node { name member { role account { name } } } } }
        }')->toArray();

        $this->assertArrayNotHasKey('errors', $result);

        $member = static fn (string $name): array => [
            'node' => ['account' => ['name' => $name], 'badges' => ['edges' => [['node' => ['name' => $name . ' badge']]]]],
        ];

        $this->assertSame([
            ['node' => ['name' => 'Red', 'members' => ['totalCount' => 2, 'edges' => [$member('ann'), $member('bob')]]]],
            ['node' => ['name' => 'Blue', 'members' => ['totalCount' => 1, 'edges' => [$member('cat')]]]],
        ], $result['data']['teams']['edges']);

        $badge = static fn (string $name): array => [
            'node' => ['name' => $name . ' badge', 'member' => ['role' => 'player', 'account' => ['name' => $name]]],
        ];

        $this->assertSame([$badge('ann'), $badge('bob'), $badge('cat')], $result['data']['badges']['edges']);
    }
}
