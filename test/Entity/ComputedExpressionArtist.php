<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity;

use ApiSkeletons\Doctrine\ORM\GraphQL\Attribute as GraphQL;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use function strlen;
use function strtoupper;

/**
 * Computed fields with expressions, by which they are filtered and sorted
 */
#[GraphQL\Entity(group: 'ComputedExpression')]
#[ORM\Entity]
class ComputedExpressionArtist
{
    private const string RECORDINGS = 'SELECT COUNT({r}.id) FROM ' . ComputedExpressionRecording::class
        . ' {r} WHERE {r}.artist = {entity}';

    #[GraphQL\Field(group: 'ComputedExpression')]
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ComputedExpressionLabel::class, inversedBy: 'artists')]
    private ComputedExpressionLabel|null $label = null;

    /** @var Collection<int, ComputedExpressionRecording> */
    #[ORM\OneToMany(targetEntity: ComputedExpressionRecording::class, mappedBy: 'artist')]
    private Collection $recordings;

    public function __construct(
        #[GraphQL\Field(group: 'ComputedExpression')]
        #[ORM\Column(type: 'string')]
        private string $name,
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

    public function getLabel(): ComputedExpressionLabel|null
    {
        return $this->label;
    }

    public function setLabel(ComputedExpressionLabel $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @return Collection<int, ComputedExpressionRecording> */
    public function getRecordings(): Collection
    {
        return $this->recordings;
    }

    #[GraphQL\ComputedField(type: 'int', group: 'ComputedExpression', expression: '(' . self::RECORDINGS . ')')]
    public function getRecordingCount(): int
    {
        return $this->recordings->count();
    }

    /** An optional argument with no default: every recording, or a year's */
    #[GraphQL\ComputedField(
        type: 'int',
        group: 'ComputedExpression',
        expression: '(' . self::RECORDINGS . ' AND ({:year} IS NULL OR {r}.released BETWEEN '
            . "CONCAT({:year}, '-01-01') AND CONCAT({:year}, '-12-31')))",
    )]
    public function getRecordingCountIn(int|null $year = null): int
    {
        return $this->recordings->filter(
            static fn (ComputedExpressionRecording $recording): bool => $year === null
                || $recording->getYear() === $year,
        )->count();
    }

    /** A required argument */
    #[GraphQL\ComputedField(
        type: 'int',
        group: 'ComputedExpression',
        expression: '(' . self::RECORDINGS . " AND {r}.released >= CONCAT({:year}, '-01-01'))",
    )]
    public function getRecordingCountSince(int $year): int
    {
        return $this->recordings->filter(
            static fn (ComputedExpressionRecording $recording): bool => $recording->getYear() >= $year,
        )->count();
    }

    /** An argument with a default value */
    #[GraphQL\ComputedField(
        type: 'int',
        group: 'ComputedExpression',
        expression: '(' . self::RECORDINGS . " AND {r}.released >= CONCAT({:year}, '-01-01'))",
    )]
    public function getRecordingCountFrom(int $year = 2003): int
    {
        return $this->getRecordingCountSince($year);
    }

    /** An argument of a type given in the attribute's args */
    #[GraphQL\ComputedField(
        type: 'int',
        group: 'ComputedExpression',
        args: ['date' => 'date_immutable'],
        expression: '(' . self::RECORDINGS . ' AND {r}.released < {:date})',
    )]
    public function getRecordingCountBefore(DateTimeImmutable $date): int
    {
        return $this->recordings->filter(
            static fn (ComputedExpressionRecording $recording): bool => $recording->getReleased() < $date,
        )->count();
    }

    /** A string, whose own excluded filters limit its filters */
    #[GraphQL\ComputedField(
        type: 'string',
        group: 'ComputedExpression',
        expression: 'UPPER({entity}.name)',
        excludeFilters: [Filters::IN, Filters::NOTIN],
    )]
    public function getUpperName(): string
    {
        return strtoupper($this->name);
    }

    /** A function, to which every filter applies, unlike a subquery */
    #[GraphQL\ComputedField(type: 'int', group: 'ComputedExpression', expression: 'LENGTH({entity}.name)')]
    public function getNameLength(): int
    {
        return strlen($this->name);
    }

    /** No expression: not filtered */
    #[GraphQL\ComputedField(type: 'string', group: 'ComputedExpression')]
    public function getDisplayName(): string
    {
        return 'The ' . $this->name;
    }
}
