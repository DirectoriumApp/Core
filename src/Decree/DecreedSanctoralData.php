<?php

declare(strict_types=1);

namespace Directorium\Core\Decree;

use Directorium\Core\Overlay\OverlayOperation;
use Directorium\Core\Sanctoral\SanctoralData;
use Directorium\Core\Sanctoral\SanctoralEntry;
use Directorium\Core\Temporal\TemporalCalendar;

/**
 * The base sanctoral of an edition with every in-force decree operation applied for
 * one resolution year — a year-aware {@see SanctoralData} decorator.
 *
 * It is the decree analogue of {@see \Directorium\Core\Overlay\OverlaidSanctoralData}:
 * both layer add / rerank / suppress operations over a base source before precedence
 * runs, so the resolver's precedence, concurrence, and commemoration logic is
 * untouched — only the *data* it reads changes. The difference is the gate. A
 * particular-calendar overlay is always in force; a decree is in force only from its
 * effective date, so this decorator is constructed for a specific year and applies an
 * operation only when the feast it targets falls on or after the decree's effective
 * date THAT year (docs/design/edition-governance.md). Decrees are applied in effective
 * order, so a later decree building on an earlier one composes correctly.
 *
 * Because the year is needed to gate, the decorator cannot be baked once at resolver
 * construction (a resolver resolves any year); {@see DecreeSet::applyTo()} builds it
 * per year inside the resolution sweep. The version stamp is the base's unchanged: the
 * corpus version already moves when a decree file is added, so it identifies the build.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class DecreedSanctoralData implements SanctoralData
{
    private SanctoralData $base;

    /** @var list<Decree> Sorted by effective date, then id (as {@see DecreeSet} holds them). */
    private array $decrees;

    private int $year;

    /**
     * @param list<Decree> $decrees
     */
    public function __construct(SanctoralData $base, array $decrees, int $year)
    {
        $this->base = $base;
        $this->decrees = $decrees;
        $this->year = $year;
    }

    /** @return list<SanctoralEntry> */
    public function entries(): array
    {
        $byId = [];
        foreach ($this->base->entries() as $entry) {
            $byId[$entry->identity()->id()->toString()] = $entry;
        }

        foreach ($this->decrees as $decree) {
            foreach ($decree->sanctoralOperations() as $operation) {
                if ($this->inForce($operation, $decree, $byId)) {
                    $byId = $operation->applyTo($byId);
                }
            }
        }

        return array_values($byId);
    }

    public function version(): string
    {
        return $this->base->version();
    }

    /**
     * Whether a sanctoral operation is in force for the resolution year: the feast it
     * targets falls, this year, on or after the decree's effective date.
     *
     * For a rerank or suppress the target is a base feast whose (month, day) — read
     * from the working entries — gives its date this year, so the gate is exact to the
     * day: raising Mary Magdalene (22 July) reads a memorial in 2015 and a feast in
     * 2016, the decree's effective year. An add's target is by definition not yet in
     * the base, so its date cannot be read here; such an operation gates by effective
     * year (no shipped decree adds a fixed-date feast — a decree that installs a
     * movable memorial does so through {@see MovableDecree}, gated per date there).
     *
     * @param array<string, SanctoralEntry> $byId
     */
    private function inForce(OverlayOperation $operation, Decree $decree, array $byId): bool
    {
        $entry = $byId[$operation->targetId()->toString()] ?? null;
        if ($entry === null) {
            return (int) $decree->effective()->format('Y') <= $this->year;
        }

        $date = TemporalCalendar::utcDate($this->year, $entry->month(), $entry->day());

        return $date >= $decree->effective();
    }
}
