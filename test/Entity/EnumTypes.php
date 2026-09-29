<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum\Priority;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Enum\Size;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fields mapped with an enumType
 */
#[GraphQL\Entity(group: 'EnumTypes')]
#[GraphQL\Entity(group: 'EnumTypesByReference', extractByValue: false)]
#[ORM\Entity]
class EnumTypes
{
    #[GraphQL\Field(group: 'EnumTypes')]
    #[GraphQL\Field(group: 'EnumTypesByReference')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function __construct(
        #[GraphQL\Field(group: 'EnumTypes')]
        #[GraphQL\Field(group: 'EnumTypesByReference')]
        #[ORM\Column(type: 'string', enumType: Size::class)]
        private Size $size,
        #[GraphQL\Field(group: 'EnumTypes')]
        #[GraphQL\Field(group: 'EnumTypesByReference')]
        #[ORM\Column(type: 'integer', enumType: Priority::class)]
        private Priority $priority,
        #[GraphQL\Field(group: 'EnumTypes')]
        #[GraphQL\Field(group: 'EnumTypesByReference')]
        #[ORM\Column(type: 'string', nullable: true, enumType: Size::class)]
        private Size|null $optionalSize = null,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSize(): Size
    {
        return $this->size;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function getOptionalSize(): Size|null
    {
        return $this->optionalSize;
    }
}
