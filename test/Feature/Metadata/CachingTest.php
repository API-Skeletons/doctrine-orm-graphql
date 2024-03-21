<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity\EntityTypeManager;
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
        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeManager::class)->get(User::class));
    }

    public function testStaticMetadata(): void
    {
        $driver            = new Driver($this->getEntityManager(), new Config(['group' => 'StaticMetadata']));
        $generatedMetadata = $driver->get('metadata')->getArrayCopy();

        $metadata = [
            'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User' => [
                'entityClass' => 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User',
                'byValue' => true,
                'limit' => 0,
                'description' => '',
                'hydratorNamingStrategy' => null,
                'hydratorFilters' => [],
                'excludeFilters' => [],
                'typeName' => 'ApiSkeletonsTest_Doctrine_ORM_GraphQL_Entity_User_StaticMetadata',
                'fields' => [
                    'name' => [
                        'description' => null,
                        'type' => 'string',
                        'hydratorStrategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                        'excludeFilters' => [],
                    ],
                    'recordings' => [
                        'limit' => null,
                        'description' => null,
                        'criteriaEventName' => null,
                        'hydratorStrategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
                        'excludeFilters' => ['eq'],
                    ],
                ],
            ],
        ];

        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'StaticMetadata']), $metadata);

        $this->assertEquals($generatedMetadata, $metadata);
        $this->assertEquals($generatedMetadata, $driver->get('metadata')->getArrayCopy());

        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeManager::class)->get(User::class));

        $this->expectException(Error::class);
        $this->assertInstanceOf(Entity::class, $driver->get(EntityTypeManager::class)->get(Artist::class));
    }
}
