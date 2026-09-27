<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Type;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Album;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;

/**
 * A to-one association field is described by its #[Association] description,
 * falling back to the description of the target entity
 */
class AssociationDescriptionTest extends TestCase
{
    public function testToOneAssociationUsesAssociationDescription(): void
    {
        $driver = new Driver($this->getEntityManager());

        $this->assertSame(
            'Artist entity',
            $driver->type(Performance::class)->getField('artist')->description,
        );
    }

    public function testToOneAssociationFallsBackToTargetEntityDescription(): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'MappedSuperclassTest']));

        $this->assertSame(
            'Artists in a release',
            $driver->type(Album::class)->getField('artist')->description,
        );
    }
}
