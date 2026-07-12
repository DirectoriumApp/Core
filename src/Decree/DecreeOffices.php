<?php

declare(strict_types=1);

namespace Directorium\Core\Decree;

use DateTimeImmutable;
use Directorium\Core\Calendar\RealizedObservance;

/**
 * The movable offices a year's in-force decrees add, keyed by date — the decree
 * counterpart of the movable-feast calendar the resolver gathers.
 *
 * A decree's fixed-date changes are folded into the sanctoral before precedence
 * ({@see DecreedSanctoralData}); its *movable* additions, which no fixed-date entry
 * can express, are realized here onto their Easter-relative dates for the year and
 * offered to the resolver as ordinary candidates through {@see on()} — exactly as
 * {@see \Directorium\Core\Temporal\MovableFeastCalendar} offers the movable feasts of
 * the Lord. Empty for every edition and year with no in-force movable decree, so it is
 * inert for the traditional editions and for a Novus-Ordo year before any such decree
 * took force (docs/design/edition-governance.md).
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class DecreeOffices
{
    /** @var array<string, RealizedObservance> Keyed by 'Y-m-d'. */
    private array $byDate;

    /**
     * @param array<string, RealizedObservance> $byDate
     */
    public function __construct(array $byDate)
    {
        $this->byDate = $byDate;
    }

    /** The decree office on a date, or null if none falls there. */
    public function on(DateTimeImmutable $date): ?RealizedObservance
    {
        return $this->byDate[$date->format('Y-m-d')] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->byDate === [];
    }
}
