<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity exposed in no group, which UnexposedTargetOwner's associations
 * refer to
 */
#[ORM\Entity]
class UnexposedTargetItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: UnexposedTargetOwner::class, inversedBy: 'items')]
    private UnexposedTargetOwner|null $owner = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): UnexposedTargetOwner|null
    {
        return $this->owner;
    }
}
