<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

use function count;

/**
 * Methods whose parameters cannot be arguments, each in its own group
 */
#[GraphQL\Entity(group: 'ComputedArgsUnsupportedType')]
#[GraphQL\Entity(group: 'ComputedArgsUnionType')]
#[GraphQL\Entity(group: 'ComputedArgsVariadic')]
#[GraphQL\Entity(group: 'ComputedArgsByReference')]
#[GraphQL\Entity(group: 'ComputedArgsObjectDefault')]
#[GraphQL\Entity(group: 'ComputedArgsUnknownArg')]
#[GraphQL\Entity(group: 'ComputedArgsNotInput')]
#[ORM\Entity]
class ComputedArgsInvalid
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }

    /** @param int[] $values */
    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgsUnsupportedType')]
    public function getUnsupportedType(array $values): int
    {
        return count($values);
    }

    #[GraphQL\ComputedField(type: 'string', group: 'ComputedArgsUnionType')]
    public function getUnionType(int|string $value): string
    {
        return (string) $value;
    }

    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgsVariadic')]
    public function getVariadic(int ...$values): int
    {
        return count($values);
    }

    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgsByReference')]
    public function getByReference(int &$value): int
    {
        return $value;
    }

    #[GraphQL\ComputedField(type: 'boolean', group: 'ComputedArgsObjectDefault', args: ['date' => 'date_immutable'])]
    public function getObjectDefault(DateTimeImmutable $date = new DateTimeImmutable('2020-01-01')): bool
    {
        return $date > new DateTimeImmutable('2000-01-01');
    }

    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgsUnknownArg', args: ['other' => 'int'])]
    public function getUnknownArg(int $value): int
    {
        return $value;
    }

    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgsNotInput', args: ['page' => 'pageinfo'])]
    public function getNotInput(mixed $page): int
    {
        return 0;
    }
}
