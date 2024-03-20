<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\EntityDefinition;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToInteger;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\Entity;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\EntityTypeManager;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Recording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User;
use ArrayObject;
use League\Event\EventDispatcher;

use function array_keys;
use function array_values;
use function sort;

class EntityTest extends AbstractTest
{
    public function testEntityMetadata(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'entityTest']));

        $entity = $driver->get(EntityTypeManager::class)->get(Recording::class);

        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertEquals(Recording::class, $entity->getEntityClass());
        $this->assertEquals('Entity Test Recordings', $entity->getDescription());

        $metadata = $entity->getMetadata();

        $this->assertEquals(1, $metadata['byValue']);
        $this->assertEquals(null, $metadata['hydratorNamingStrategy']);

        $this->assertEquals(
            ToInteger::class,
            $metadata['fields']['id']['hydratorStrategy'],
        );
        $this->assertEquals(
            FieldDefault::class,
            $metadata['fields']['source']['hydratorStrategy'],
        );
        $this->assertEquals(
            AssociationDefault::class,
            $metadata['fields']['performance']['hydratorStrategy'],
        );
        $this->assertEquals(
            AssociationDefault::class,
            $metadata['fields']['users']['hydratorStrategy'],
        );

        $this->assertEquals([], $metadata['hydratorFilters']);

        $this->assertEquals('Entity Test Recordings', $metadata['description']);
        $this->assertEquals('Entity Test ID', $metadata['fields']['id']['description']);
        $this->assertEquals('Entity Test Source', $metadata['fields']['source']['description']);
        $this->assertEquals('Entity Test Performance', $metadata['fields']['performance']['description']);
        $this->assertEquals('Entity Test Users', $metadata['fields']['users']['description']);

        $this->assertEquals('entitytestrecording_entityTest', $metadata['typeName']);
    }

    public function testSortFields(): void
    {
        $unsortedFields           = new ArrayObject();
        $unsortedFields['fields'] = [];

        $config = new Config(['sortFields' => true]);

        $driver = new Driver($this->getEntityManager(), $config);

        // Fields are only sorted after this event is fired
        $driver->get(EventDispatcher::class)->subscribeTo(
            User::class . '.definition',
            static function (EntityDefinition $event) use ($unsortedFields): void {
                $fields = $event->getDefinition()['fields']();

                $unsortedFields['fields'] = array_keys($fields);
            },
        );

        $graphQLType  = $driver->type(User::class);
        $fields       = array_keys($graphQLType->getFields());
        $fieldsSorted = $fields;
        sort($fieldsSorted);
        $unsortedFieldsSorted = $unsortedFields['fields'];
        sort($unsortedFieldsSorted);

        $this->assertNotEquals(array_values($unsortedFields['fields']), $fields);
        $this->assertEquals($fields, $fieldsSorted);
        $this->assertEquals($fields, $unsortedFieldsSorted);
    }
}
