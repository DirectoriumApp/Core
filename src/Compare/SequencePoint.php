<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

use DateTimeImmutable;

/**
 * One step in a {@see ComparedSequence}: a resolved {@see EditionDayCell} at a labelled
 * position on the sequence axis (a year, or an edition), tagged with the
 * {@see ComparisonField}s that changed from the previous step.
 *
 * The first point of a sequence has no predecessor, so its `changes` are empty.
 */
final class SequencePoint
{
    private string $key;

    private DateTimeImmutable $date;

    private EditionDayCell $cell;

    /** @var list<string> */
    private array $changes;

    /**
     * @param list<string> $changes
     */
    public function __construct(string $key, DateTimeImmutable $date, EditionDayCell $cell, array $changes)
    {
        $this->key = $key;
        $this->date = $date;
        $this->cell = $cell;
        $this->changes = $changes;
    }

    /** The axis label — the year (`"2024"`) or edition urn this point sits at. */
    public function key(): string
    {
        return $this->key;
    }

    /** The civil date this point resolves (the anchor date in this point's year). */
    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function cell(): EditionDayCell
    {
        return $this->cell;
    }

    /** @return list<string> the fields that changed from the previous point (empty for the first) */
    public function changes(): array
    {
        return $this->changes;
    }

    public function changed(): bool
    {
        return $this->changes !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'date' => $this->date->format('Y-m-d'),
            'changed' => $this->changed(),
            'changes' => $this->changes,
            'cell' => $this->cell->toArray(),
        ];
    }
}
