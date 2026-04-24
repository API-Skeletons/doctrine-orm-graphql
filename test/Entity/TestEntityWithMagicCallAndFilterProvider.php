<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use BadMethodCallException;
use Doctrine\ORM\Mapping as ORM;
use Laminas\Hydrator\Filter\FilterInterface;
use Laminas\Hydrator\Filter\FilterProviderInterface;

/**
 * Variant of TestEntityWithMagicCall that also implements FilterProviderInterface.
 * Used to exercise the $object->getFilter() branch in DoctrineObjectWithComputed::extractByValue().
 */
#[ORM\Entity]
class TestEntityWithMagicCallAndFilterProvider implements FilterProviderInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $regularField;

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

    /**
     * Implements FilterProviderInterface so that extractByValue takes the
     * $object->getFilter() branch rather than $this->filterComposite.
     * This filter allows all properties through.
     */
    public function getFilter(): FilterInterface
    {
        return new class implements FilterInterface {
            public function filter(string $property, object|null $instance = null): bool
            {
                return true;
            }
        };
    }
}
