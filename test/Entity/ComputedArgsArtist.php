<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use function array_fill;
use function array_values;
use function implode;
use function strtoupper;

/**
 * Computed fields with arguments, taken from their methods' parameters
 */
#[GraphQL\Entity(group: 'ComputedArgs')]
#[ORM\Entity]
class ComputedArgsArtist
{
    #[GraphQL\Field(group: 'ComputedArgs')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    /** @var Collection<int, ComputedArgsRecording> */
    #[ORM\OneToMany(targetEntity: ComputedArgsRecording::class, mappedBy: 'artist')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $recordings;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedArgs')]
        #[ORM\Column(type: 'string')]
        private string $name,
        #[ORM\Column(type: 'date_immutable')]
        private DateTimeImmutable $founded,
    ) {
        $this->recordings = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, ComputedArgsRecording> */
    public function getRecordings(): Collection
    {
        return $this->recordings;
    }

    /** An optional argument with no default: every recording, or a year's */
    #[GraphQL\ComputedField(type: 'int', group: 'ComputedArgs')]
    public function getTotalRecordings(int|null $year = null): int
    {
        if ($year === null) {
            return $this->recordings->count();
        }

        return $this->recordings->filter(
            static fn (ComputedArgsRecording $recording): bool => $recording->getYear() === $year,
        )->count();
    }

    /** A required argument */
    #[GraphQL\ComputedField(type: 'string', group: 'ComputedArgs')]
    public function getGreeting(string $salutation): string
    {
        return $salutation . ', ' . $this->name;
    }

    /** Arguments with default values */
    #[GraphQL\ComputedField(type: 'string', group: 'ComputedArgs')]
    public function getRepeatedName(int $times = 2, string $separator = ' '): string
    {
        return implode($separator, array_fill(0, $times, $this->name));
    }

    #[GraphQL\ComputedField(type: 'float', group: 'ComputedArgs')]
    public function getScaled(float $factor = 1.5): float
    {
        return $this->recordings->count() * $factor;
    }

    #[GraphQL\ComputedField(type: 'string', group: 'ComputedArgs')]
    public function getShout(bool $loud = false): string
    {
        return $loud ? strtoupper($this->name) : $this->name;
    }

    /**
     * A list of entities, with an argument
     *
     * @return list<ComputedArgsRecording>
     */
    #[GraphQL\ComputedField(type: ComputedArgsRecording::class, group: 'ComputedArgs', list: true)]
    public function getRecordingsSince(int $year): array
    {
        return array_values($this->recordings->filter(
            static fn (ComputedArgsRecording $recording): bool => $recording->getYear() >= $year,
        )->toArray());
    }

    /** An argument of a type given in the attribute's args */
    #[GraphQL\ComputedField(type: 'boolean', group: 'ComputedArgs', args: ['date' => 'date_immutable'])]
    public function getFoundedBefore(DateTimeImmutable $date): bool
    {
        return $this->founded < $date;
    }

    /** No arguments */
    #[GraphQL\ComputedField(type: 'string', group: 'ComputedArgs')]
    public function getDisplayName(): string
    {
        return 'The ' . $this->name;
    }
}
