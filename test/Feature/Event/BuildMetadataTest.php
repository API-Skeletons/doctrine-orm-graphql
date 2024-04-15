<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Event;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\AbstractTest;
use ArrayObject;
use League\Event\Emitter;
use League\Event\Event;

/**
 * This test uses both EventDefinition and QueryBuidlerTest to add a new
 * field to an entity type and filter it.
 */
class BuildMetadataTest extends AbstractTest
{
    public function testEvent(): void
    {
        $test = $this;

        $driver = new Driver($this->getEntityManager());

        $driver->get(Emitter::class)->addListener(
            'metadata.build',
            static function (Event $leagueEvent, Metadata $event) use ($test): void {
                $metadata = $event->getMetadata();

                $test->assertEquals('metadata.build', $leagueEvent->getName());
                $test->assertInstanceOf(ArrayObject::class, $event->getMetadata());
                $test->assertEquals(0, $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit']);

                $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit'] = 100;
            },
        );

        $metadata = $driver->get('metadata');

        $this->assertInstanceOf(ArrayObject::class, $metadata);
        $test->assertEquals(100, $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit']);
    }

    public function testEventWithGlobalEnable(): void
    {
        $test = $this;

        $driver = new Driver($this->getEntityManager(), new Config(['globalEnable' => true]));

        $driver->get(Emitter::class)->addListener(
            'metadata.build',
            static function (Event $leagueEvent, Metadata $event) use ($test): void {
                $metadata = $event->getMetadata();

                $test->assertEquals('metadata.build', $leagueEvent->getName());
                $test->assertInstanceOf(ArrayObject::class, $event->getMetadata());
                $test->assertEquals(0, $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit']);

                $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit'] = 100;
            },
        );

        $metadata = $driver->get('metadata');

        $this->assertInstanceOf(ArrayObject::class, $metadata);
        $test->assertEquals(100, $metadata['ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance']['limit']);
    }
}
