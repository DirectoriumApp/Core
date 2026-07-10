<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

use DateTimeImmutable;

/**
 * How a single date resolves across the compared editions: one {@see EditionDayCell}
 * per edition, plus the list of {@see ComparisonField}s on which the editions do not
 * all agree.
 *
 * `divergences === []` means every compared edition celebrates the day identically on
 * every field — the "only-changed" filter of a comparison UI hides exactly these.
 */
final class ComparedDay
{
    private DateTimeImmutable $date;

    /** @var array<string, EditionDayCell> keyed by edition urn, in edition order */
    private array $cells;

    /** @var list<string> */
    private array $divergences;

    /**
     * @param array<string, EditionDayCell> $cells
     * @param list<string> $divergences
     */
    private function __construct(DateTimeImmutable $date, array $cells, array $divergences)
    {
        $this->date = $date;
        $this->cells = $cells;
        $this->divergences = $divergences;
    }

    /**
     * @param array<string, EditionDayCell> $cells keyed by edition urn, in edition order
     */
    public static function of(DateTimeImmutable $date, array $cells): self
    {
        return new self($date, $cells, self::divergentFields($cells));
    }

    /**
     * The fields on which the cells do not all share one value.
     *
     * @param array<string, EditionDayCell> $cells
     *
     * @return list<string>
     */
    private static function divergentFields(array $cells): array
    {
        $divergent = [];
        foreach (ComparisonField::all() as $field) {
            $values = [];
            foreach ($cells as $cell) {
                $values[] = $cell->comparableValues()[$field];
            }
            if (!self::allEqual($values)) {
                $divergent[] = $field;
            }
        }

        return $divergent;
    }

    /**
     * @param list<mixed> $values
     */
    private static function allEqual(array $values): bool
    {
        if (count($values) <= 1) {
            return true;
        }

        $first = $values[0];
        foreach ($values as $value) {
            if ($value !== $first) {
                return false;
            }
        }

        return true;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /** @return array<string, EditionDayCell> */
    public function cells(): array
    {
        return $this->cells;
    }

    public function cell(string $edition): ?EditionDayCell
    {
        return $this->cells[$edition] ?? null;
    }

    /** @return list<string> */
    public function divergences(): array
    {
        return $this->divergences;
    }

    public function isDivergent(): bool
    {
        return $this->divergences !== [];
    }

    /** Whether every compared edition agrees on this field. */
    public function agreesOn(string $field): bool
    {
        return !in_array($field, $this->divergences, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $cells = [];
        foreach ($this->cells as $edition => $cell) {
            $cells[$edition] = $cell->toArray();
        }

        return [
            'date' => $this->date->format('Y-m-d'),
            'editions' => array_keys($this->cells),
            'divergent' => $this->isDivergent(),
            'divergences' => $this->divergences,
            'cells' => $cells,
        ];
    }
}
