<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Golden;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\TemporalCalendar;

/**
 * The golden-fixture safety gate (#365): a byte-stable digest of the engine's
 * resolved output for every civil year the resolver can produce, 1584–2200.
 *
 * The corpus epic replaces inline PHP logic with generated data in stages — the
 * sanctoral swap (#41), the temporal definitions (#42), and the precedence table
 * (#43). Each of those is meant to be *inert*: data replaces code without moving
 * the resolved calendar by a byte. This class freezes that output so any
 * accidental drift fails the build.
 *
 * What is frozen is the *liturgical* resolution, not its provenance stamps. The
 * {@see DayContract} carries a `corpusVersion` and `engineVersion` that are
 * *expected* to change when the data source is swapped or the engine is bumped;
 * those two fields are stripped before hashing so the digest answers exactly one
 * question — "did the computed liturgy change?" — and the #41 plumbing swap
 * (same feasts, new corpus id) passes cleanly. Everything else the contract emits
 * (dates, offices, ranks, colours, outcomes, transfers, seasons, the shape
 * version) is included, so a real change is caught.
 *
 * The digest per year is the SHA-256 of {@see serializeYear()}: each day in
 * ascending date order serialised to the contract's frozen JSON (minus the two
 * version stamps), joined by a newline. Because the contract serialisation is
 * itself deterministic, the same inputs yield the same digest on every platform
 * and PHP version.
 */
final class GoldenYear
{
    /**
     * The first year the *whole-year resolver* can produce.
     *
     * The engine's Gregorian floor is 1583 (the first Gregorian Easter), but
     * {@see DayResolver::resolveYear()} reaches back one year for the trailing
     * Christmas cycle that bleeds into January ({@see \Directorium\Core\Temporal\ChristmasCycle}
     * `forYear($year - 1)`). Resolving 1583 would therefore need the 1582 cycle,
     * below the floor, so the first fully resolvable civil year is 1584.
     */
    public const FIRST_YEAR = 1584;

    /** The last year frozen — a horizon well beyond any practical query. */
    public const LAST_YEAR = 2200;

    /** The provenance fields stripped before hashing: metadata, not liturgy. */
    private const VOLATILE_FIELDS = ['corpusVersion', 'engineVersion'];

    /** The committed fixture: one JSON object per line, ascending by year. */
    public static function fixturePath(): string
    {
        return __DIR__ . '/resolve-year-digests.ndjson';
    }

    /**
     * The digest of one year's resolved liturgy — the SHA-256 of its byte-stable
     * serialisation.
     */
    public static function digestFor(int $year): string
    {
        return hash('sha256', self::serializeYear($year));
    }

    /**
     * The exact preimage of {@see digestFor()}: the year's days in ascending
     * order, each the output contract's JSON with the volatile provenance stamps
     * removed, joined by newlines. Exposed so a failing gate can be diffed day by
     * day, not just by an opaque hash.
     */
    public static function serializeYear(int $year): string
    {
        $resolved = DayResolver::for1962()->resolveYear($year);
        $provenance = $resolved->provenance();

        $lines = [];
        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        $oneDay = new DateInterval('P1D');

        while ($date <= $end) {
            $array = DayContract::from($resolved->day($date), $provenance)->toArray();
            foreach (self::VOLATILE_FIELDS as $field) {
                unset($array[$field]);
            }
            $lines[] = json_encode(
                $array,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $date = $date->add($oneDay);
        }

        return implode("\n", $lines);
    }

    /**
     * The number of resolved days in a year — 365, or 366 in a Gregorian leap
     * year — recorded alongside each digest as a cheap human-readable sanity flag.
     */
    public static function dayCount(int $year): int
    {
        return (int) TemporalCalendar::utcDate($year, 12, 31)->format('z') + 1;
    }

    /**
     * The full fixture text: every year FIRST_YEAR..LAST_YEAR as one NDJSON line
     * `{"year":Y,"days":N,"sha256":"…"}`, ascending, terminated by a trailing
     * newline. This is what {@see fixturePath()} holds and what the freeze script
     * writes; regenerate it only when a calendar change has been reviewed.
     */
    public static function freezeText(): string
    {
        $out = '';
        for ($year = self::FIRST_YEAR; $year <= self::LAST_YEAR; $year++) {
            $out .= json_encode(
                ['year' => $year, 'days' => self::dayCount($year), 'sha256' => self::digestFor($year)],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n";
        }

        return $out;
    }
}
