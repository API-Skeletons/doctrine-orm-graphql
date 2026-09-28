<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\EntityDbal4;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use BcMath\Number;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use stdClass;

/**
 * Doctrine types added in DBAL 4.  This entity is not in test/Entity, whose
 * schema is created with DBAL 3 too.
 */
#[GraphQL\Entity]
#[ORM\Entity]
class Dbal4Types
{
    #[GraphQL\Field]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @param mixed[] $jsonb */
    public function __construct(
        #[GraphQL\Field]
        #[ORM\Column(type: 'smallfloat')]
        private float $smallFloat,
        #[GraphQL\Field]
        #[ORM\Column(type: 'number', precision: 10, scale: 2)]
        private Number $number,
        #[GraphQL\Field]
        #[ORM\Column(type: 'enum', options: ['values' => ['small', 'large']])]
        private string $enum,
        #[GraphQL\Field]
        #[ORM\Column(type: 'json_object')]
        private stdClass $jsonObject,
        #[GraphQL\Field]
        #[ORM\Column(type: 'jsonb')]
        private array $jsonb,
        #[GraphQL\Field]
        #[ORM\Column(type: 'jsonb_object')]
        private stdClass $jsonbObject,
        #[GraphQL\Field]
        #[ORM\Column(type: 'datetime_utc')]
        private DateTime $dateTimeUtc,
        #[GraphQL\Field]
        #[ORM\Column(type: 'datetime_utc_immutable')]
        private DateTimeImmutable $dateTimeUtcImmutable,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSmallFloat(): float
    {
        return $this->smallFloat;
    }

    public function getNumber(): Number
    {
        return $this->number;
    }

    public function getEnum(): string
    {
        return $this->enum;
    }

    public function getJsonObject(): stdClass
    {
        return $this->jsonObject;
    }

    /** @return mixed[] */
    public function getJsonb(): array
    {
        return $this->jsonb;
    }

    public function getJsonbObject(): stdClass
    {
        return $this->jsonbObject;
    }

    public function getDateTimeUtc(): DateTime
    {
        return $this->dateTimeUtc;
    }

    public function getDateTimeUtcImmutable(): DateTimeImmutable
    {
        return $this->dateTimeUtcImmutable;
    }
}
