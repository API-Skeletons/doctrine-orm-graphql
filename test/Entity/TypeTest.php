<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * TypeTest
 *
 * @phpcs:ignoreFile
 */
#[GraphQL\Entity(typeName: 'typeTest', description: 'Type test')]
#[GraphQL\Entity(group: 'DataTypesTest')]
#[GraphQL\Entity(group: 'CustomTypeTest')]
#[GraphQL\Entity(group: 'BetweenTypeContainerTest')]

#[ORM\Entity]
class TypeTest
{
    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[GraphQL\Field(group: 'BetweenTypeContainerTest')]
    #[ORM\Column(type: "integer", nullable: false)]
    private int $testInt;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[GraphQL\Field(group: 'BetweenTypeContainerTest', excludeFilters: [Filters::EQ])]
    private int $testInt2;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "datetime", nullable: false)]
    private DateTime $testDateTime;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[GraphQL\Field(group: 'CustomTypeTest', type: 'customType')]
    #[ORM\Column(type: "float", nullable: false, scale: 5, precision: 8)]
    private float $testFloat;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "boolean", nullable: false)]
    private bool $testBool;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "text", nullable: false)]
    private string $testText;

    #[GraphQL\Field]
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Id]
    #[ORM\Column(type: "bigint")]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private int $id;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "bigint", nullable: false)]
    private string $testBigint;

    /**
     * @var DateTimeImmutable
     */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "date_immutable", nullable: false)]
    private $testDateImmutable;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "date", nullable: false)]
    private DateTime $testDate;

    /**
     * @var DateTimeImmutable
     */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "datetime_immutable", nullable: false)]
    private $testDateTimeImmutable;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "datetimetz", nullable: false)]
    private DateTime $testDateTimeTZ;

    /**
     * @var DateTimeImmutable
     */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "datetimetz_immutable", nullable: false)]
    private $testDateTimeTZImmutable;

    /**
     * @var DateTimeImmutable
     */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "time_immutable", nullable: false)]
    private $testTimeImmutable;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "time", nullable: false)]
    private DateTime $testTime;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "decimal", nullable: false, precision: 8, scale: 5)]
    private float $testDecimal;

    #[GraphQL\Field(group: 'DataTypesTest')]
    private string $testGuid;

    /** @var mixed[] */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "json", nullable: false)]
    private array $testJson;

    /** @var string[] */
    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "simple_array", nullable: false)]
    private array $testSimpleArray;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "smallint", nullable: false)]
    private int $testSmallInt;

    #[GraphQL\Field(group: 'DataTypesTest')]
    #[ORM\Column(type: "blob", nullable: false)]
    private mixed $testBlob;

    public function getTestBigint(): mixed
    {
        return $this->testBigint;
    }

    public function setTestBigint(mixed $testBigint): self
    {
        $this->testBigint = $testBigint;

        return $this;
    }

    public function getTestDate(): mixed
    {
        return $this->testDate;
    }

    public function setTestDate(mixed $testDate): self
    {
        $this->testDate = $testDate;

        return $this;
    }

    public function getTestDateImmutable(): mixed
    {
        return $this->testDateImmutable;
    }

    public function setTestDateImmutable(mixed $testDateImmutable): self
    {
        $this->testDateImmutable = $testDateImmutable;

        return $this;
    }

    public function getTestDateTimeImmutable(): mixed
    {
        return $this->testDateTimeImmutable;
    }

    public function setTestDateTimeImmutable(mixed $testDateTimeImmutable): self
    {
        $this->testDateTimeImmutable = $testDateTimeImmutable;

        return $this;
    }

    public function getTestDateTimeTZ(): mixed
    {
        return $this->testDateTimeTZ;
    }

    public function setTestDateTimeTZ(mixed $testDateTimeTZ): self
    {
        $this->testDateTimeTZ = $testDateTimeTZ;

        return $this;
    }

    public function getTestDateTimeTZImmutable(): mixed
    {
        return $this->testDateTimeTZImmutable;
    }

    public function setTestDateTimeTZImmutable(mixed $testDateTimeTZImmutable): self
    {
        $this->testDateTimeTZImmutable = $testDateTimeTZImmutable;

        return $this;
    }

    public function getTestDecimal(): mixed
    {
        return $this->testDecimal;
    }

    public function setTestDecimal(mixed $testDecimal): self
    {
        $this->testDecimal = $testDecimal;

        return $this;
    }

    public function getTestGuid(): mixed
    {
        return $this->testGuid;
    }

    public function setTestGuid(mixed $testGuid): self
    {
        $this->testGuid = $testGuid;

        return $this;
    }

    public function getTestJson(): mixed
    {
        return $this->testJson;
    }

    public function setTestJson(mixed $testJson): self
    {
        $this->testJson = $testJson;

        return $this;
    }

    public function getTestSimpleArray(): mixed
    {
        return $this->testSimpleArray;
    }

    public function setTestSimpleArray(mixed $testSimpleArray): self
    {
        $this->testSimpleArray = $testSimpleArray;

        return $this;
    }

    public function getTestSmallInt(): mixed
    {
        return $this->testSmallInt;
    }

    public function setTestSmallInt(mixed $testSmallInt): self
    {
        $this->testSmallInt = $testSmallInt;

        return $this;
    }

    public function getTestTime(): mixed
    {
        return $this->testTime;
    }

    public function setTestTime(mixed $testTime): self
    {
        $this->testTime = $testTime;

        return $this;
    }

    public function getTestTimeImmutable(): mixed
    {
        return $this->testTimeImmutable;
    }

    public function setTestTimeImmutable(mixed $testTimeImmutable): self
    {
        $this->testTimeImmutable = $testTimeImmutable;

        return $this;
    }


    /**
     * Set testInt.
     */
    public function setTestInt(int $testInt): TypeTest
    {
        $this->testInt = $testInt;

        return $this;
    }

    /**
     * Get testInt.
     */
    public function getTestInt(): int
    {
        return $this->testInt;
    }

    /**
     * Set testDateTime.
     */
    public function setTestDateTime(DateTime $testDateTime): TypeTest
    {
        $this->testDateTime = $testDateTime;

        return $this;
    }

    /**
     * Get testDateTime.
     */
    public function getTestDateTime(): DateTime
    {
        return $this->testDateTime;
    }

    /**
     * Set testFloat.
     */
    public function setTestFloat(float $testFloat): TypeTest
    {
        $this->testFloat = $testFloat;

        return $this;
    }

    /**
     * Get testFloat.
     */
    public function getTestFloat(): float
    {
        return $this->testFloat;
    }

    /**
     * Set testBool.
     */
    public function setTestBool(bool $testBool): TypeTest
    {
        $this->testBool = $testBool;

        return $this;
    }

    /**
     * Get testBool.
     */
    public function getTestBool(): bool
    {
        return $this->testBool;
    }

    /**
     * Set testText.
     */
    public function setTestText(string $testText): TypeTest
    {
        $this->testText = $testText;

        return $this;
    }

    /**
     * Get testText.
     */
    public function getTestText(): string
    {
        return $this->testText;
    }

    /**
     * Get id.
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getTestBlob(): mixed
    {
        return $this->testBlob;
    }

    public function setTestBlob(mixed $blob): self
    {
        $this->testBlob = $blob;

        return $this;
    }
}
