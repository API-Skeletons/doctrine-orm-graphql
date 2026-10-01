<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An embeddable, whose fields are not exposed
 */
#[ORM\Embeddable]
class TestEmbeddableAddress
{
    public function __construct(
        #[ORM\Column(type: 'string')]
        private string $street,
        #[ORM\Column(type: 'string')]
        private string $city,
    ) {
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}
