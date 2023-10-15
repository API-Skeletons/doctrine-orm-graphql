<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeManager;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use GraphQL\Error\Error;

class CachingTest extends AbstractTest
{
    public function testCacheMetadata(): void
    {
        $driver = new Driver($this->getEntityManager());

        $metadata = $driver->get('metadata');

        unset($driver);

        $driver = new Driver($this->getEntityManager(), null, $metadata->getArrayCopy());
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, User::class));
    }

    public function testStaticMetadata(): void
    {
        $metadata = [
            'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User' => [
                'entityClass' => 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User',
                'documentation' => '',
                'byValue' => 1,
                'namingStrategy' => null,
                'fields' => [
                    'name' => [
                        'strategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                        'documentation' => '',
                    ],
                    'recordings' => [
                        'strategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
                        'excludeCriteria' => ['eq'],
                        'documentation' => '',
                    ],
                ],

                'strategies' => [
                    'name' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                    'email' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                    'id' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToInteger',
                    'recordings' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
                ],
                'filters' => [],
                'typeName' => 'User',
            ],
        ];

        $driver = new Driver($this->getEntityManager(), null, $metadata);
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, User::class));

        $this->expectException(Error::class);
        $this->assertInstanceOf(Entity::class, $driver->get(TypeManager::class)->build(Entity::class, Artist::class));
    }
}
