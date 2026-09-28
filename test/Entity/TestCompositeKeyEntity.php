<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An entity with a composite identifier
 */
#[ORM\Entity]
class TestCompositeKeyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $firstId,
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $secondId,
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
    }

    public function getFirstId(): int
    {
        return $this->firstId;
    }

    public function getSecondId(): int
    {
        return $this->secondId;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
