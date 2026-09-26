<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Hydrator;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\HydratorContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\TypeTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Hydrator\Strategy\PrefixFieldName;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

/**
 * A strategy implementing this library's Strategy interface receives the
 * Doctrine field name for each field it extracts, even though one instance
 * is shared by every field which uses it.
 */
class FieldNameStrategyTest extends TestCase
{
    public function testSharedStrategyReceivesDoctrineFieldName(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'FieldNameStrategy']));
        $driver->get(HydratorContainer::class)->set(PrefixFieldName::class, static fn () => new PrefixFieldName());

        $typeTest = $this->getEntityManager()->getRepository(TypeTest::class)->find(1);

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => [
                    'typeTest' => $driver->completeConnection(TypeTest::class),
                ],
            ]),
        ]);

        $query  = '{ typeTest { edges { node { testText bigintAlias } } } }';
        $result = GraphQL::executeQuery($schema, $query);

        $this->assertEmpty($result->errors);

        $node = $result->toArray()['data']['typeTest']['edges'][0]['node'];

        $this->assertSame('testText:' . $typeTest->getTestText(), $node['testText']);

        // The Doctrine field name is passed, not the GraphQL alias
        $this->assertSame('testBigint:' . $typeTest->getTestBigint(), $node['bigintAlias']);
    }
}
