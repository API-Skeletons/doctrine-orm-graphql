<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A readonly entity class; data cannot be extracted from it by reference
 */
#[ORM\Entity]
readonly class TestReadonlyEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $id,
        #[ORM\Column(type: 'string')]
        private string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
