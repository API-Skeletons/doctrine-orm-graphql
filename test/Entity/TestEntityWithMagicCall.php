<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use BadMethodCallException;
use Doctrine\ORM\Mapping as ORM;

/**
 * Test entity that implements __call to handle getXxx() method calls.
 * Used to verify that DoctrineObjectWithComputed falls back to __call
 * when no explicit getter or isser exists for a field.
 */
#[ORM\Entity]
class TestEntityWithMagicCall
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** Field with an explicit getter — parent extractByValue handles it normally */
    #[ORM\Column(type: 'string')]
    private string $regularField;

    /** Field with no explicit getter — __call must be used to extract it */
    #[ORM\Column(type: 'string')]
    private string $magicField;

    public function getId(): int
    {
        return $this->id;
    }

    public function setRegularField(string $value): self
    {
        $this->regularField = $value;

        return $this;
    }

    public function getRegularField(): string
    {
        return $this->regularField;
    }

    public function setMagicField(string $value): self
    {
        $this->magicField = $value;

        return $this;
    }

    /**
     * Magic method — handles getMagicField() calls so extraction works
     * without an explicit getter on the class.
     *
     * @param array<mixed> $args
     */
    public function __call(string $name, array $args): mixed
    {
        if ($name === 'getMagicField') {
            return $this->magicField;
        }

        throw new BadMethodCallException('Method ' . $name . ' not found');
    }
}
