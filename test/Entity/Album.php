<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use Doctrine\ORM\Mapping as ORM;

#[GraphQL\Entity(group: 'MappedSuperclassTest')]
#[GraphQL\Entity(group: 'MappedSuperclassByReferenceTest', byValue: false)]
#[ORM\Entity]
class Album extends AbstractRelease
{
    #[GraphQL\Field(group: 'MappedSuperclassTest')]
    #[GraphQL\Field(group: 'MappedSuperclassByReferenceTest')]
    #[ORM\Column(type: 'integer', nullable: false)]
    private int $trackCount;

    public function setTrackCount(int $trackCount): self
    {
        $this->trackCount = $trackCount;

        return $this;
    }

    public function getTrackCount(): int
    {
        return $this->trackCount;
    }
}
