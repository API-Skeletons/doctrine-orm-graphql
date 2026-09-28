<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use DateInterval;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine types of DBAL 3 and 4 which are not in TypeTest
 */
#[GraphQL\Entity(group: 'DbalTypes')]
#[ORM\Entity]
class DbalTypes
{
    #[GraphQL\Field(group: 'DbalTypes')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'DbalTypes')]
        #[ORM\Column(type: 'ascii_string')]
        private string $asciiString,
        #[GraphQL\Field(group: 'DbalTypes')]
        #[ORM\Column(type: 'guid')]
        private string $guid,
        #[GraphQL\Field(group: 'DbalTypes')]
        #[ORM\Column(type: 'binary')]
        private mixed $binary,
        #[GraphQL\Field(group: 'DbalTypes')]
        #[ORM\Column(type: 'dateinterval')]
        private DateInterval $dateInterval,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAsciiString(): string
    {
        return $this->asciiString;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getBinary(): mixed
    {
        return $this->binary;
    }

    public function getDateInterval(): DateInterval
    {
        return $this->dateInterval;
    }
}
