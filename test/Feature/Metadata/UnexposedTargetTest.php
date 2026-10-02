<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Metadata;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Artist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\UnexposedTargetItem;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\UnexposedTargetOwner;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function preg_quote;

/**
 * An association to an entity which is not exposed in the group is an error
 * when the metadata is built, naming the association, rather than an error
 * of every query of its entity's type which names only the target
 */
class UnexposedTargetTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function groupProvider(): array
    {
        return [
            'collection' => ['UnexposedCollection', 'items'],
            'to-one' => ['UnexposedToOne', 'favourite'],
        ];
    }

    #[DataProvider('groupProvider')]
    public function testAssociationToAnUnexposedEntityIsAnError(string $group, string $association): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessage(
            'Association ' . $association . ' of entity ' . UnexposedTargetOwner::class . ' refers to '
            . UnexposedTargetItem::class . ', an entity which is not exposed in group ' . $group
            . '.  Add an Entity attribute of the group to it.',
        );

        $driver->get(Metadata::class);
    }

    /**
     * The entities are checked after the metadata.build event, so a listener
     * may expose the entity an association refers to
     */
    #[DataProvider('groupProvider')]
    public function testListenerMayExposeTheEntity(string $group, string $association): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => $group]));

        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $event->getMetadata()[UnexposedTargetItem::class] = [
                    'entityClass' => UnexposedTargetItem::class,
                    'extractByValue' => true,
                    'limit' => 0,
                    'description' => null,
                    'excludeFilters' => [],
                    'typeName' => 'UnexposedTargetItem',
                    'fields' => [
                        'id' => [
                            'alias' => null,
                            'description' => null,
                            'type' => 'integer',
                            'hydratorStrategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToInteger',
                            'excludeFilters' => [],
                        ],
                    ],
                ];
            },
        );

        $schema = new Schema([
            'query' => new ObjectType([
                'name' => 'query',
                'fields' => ['owners' => $driver->completeConnection(UnexposedTargetOwner::class)],
            ]),
        ]);

        $schema->assertValid();

        $owner = $driver->type(UnexposedTargetOwner::class);
        $this->assertInstanceOf(ObjectType::class, $owner);
        $this->assertTrue($owner->hasField($association));
    }

    /**
     * A listener which removes an entity an association refers to makes the
     * association an error
     */
    public function testListenerMayRemoveTheEntity(): void
    {
        $driver = new Driver($this->getEntityManager());

        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                unset($event->getMetadata()[Artist::class]);
            },
        );

        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches(
            '/^Association \w+ of entity [\w\\\\]+ refers to ' . preg_quote(Artist::class, '/')
            . ', an entity which is not exposed in group default\./',
        );

        $driver->get(Metadata::class);
    }

    /**
     * Only entities are checked, so a listener's own key in the metadata is
     * not given to Doctrine as an entity
     */
    public function testKeyWhichIsNotAnEntityIsNotChecked(): void
    {
        $driver = new Driver($this->getEntityManager());

        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event): void {
                $event->getMetadata()['listener note'] = ['fields' => ['artist' => []]];
            },
        );

        $this->assertSame(['fields' => ['artist' => []]], $driver->get(Metadata::class)['listener note']);
    }
}
