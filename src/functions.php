<?php

declare(strict_types=1);

namespace Introibo\Core;

use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Contract\DayContract;
use Introibo\Core\Overlay\CalendarCatalog;
use Introibo\Core\Precedence\ResolvedYear;

/**
 * The engine entry point: the liturgical day for a civil date.
 *
 * Returns the resolved {@see LiturgicalDay} — its celebration, commemorations,
 * displaced offices, and tempora. Because a transferred feast makes any day depend
 * on earlier ones, the whole civil year is resolved once and memoised for the life
 * of the process, so repeated calls within a year are cheap. A {@see DateTimeImmutable}
 * is required so the returned day cannot be changed out from under the caller.
 *
 * `$calendar` selects the particular calendar to resolve under (#78): null for the
 * universal calendar, or a calendar named by its slug (`sspx`) or overlay URN
 * (`introibo:overlay:roman:sspx`). `$rubricSystem` selects the edition (rules-family):
 * null for the default 1962 (Rubricae 1960), or `roman:divino-afflatu` (1954) /
 * `roman:rubricae-1955` (1955), or a friendly alias (`1954`, `1955`, `1962`). The two
 * selectors are orthogonal (an overlay is layered over an edition), and both default so
 * `day($date)` resolves the universal 1962 calendar exactly as before.
 */
function day(DateTimeImmutable $date, ?string $calendar = null, ?string $rubricSystem = null): LiturgicalDay
{
    return resolvedYear((int) $date->format('Y'), $calendar, $rubricSystem)->day($date);
}

/**
 * The public output contract for a civil date: the same resolved day as
 * {@see day()}, serialised to the versioned, JSON-ready structure the other
 * Introibo repos build on (see {@see DayContract} and
 * docs/design/output-contract.md).
 *
 * `$calendar` selects the particular calendar as for {@see day()} (stamped into the
 * contract's `calendar` block); `$rubricSystem` selects the edition (stamped as the
 * contract's `edition` provenance axis).
 *
 * @return array<string, mixed>
 */
function contract(
    DateTimeImmutable $date,
    bool $explain = false,
    ?string $calendar = null,
    ?string $rubricSystem = null
): array {
    $year = $explain
        ? explainedYear((int) $date->format('Y'), $calendar, $rubricSystem)
        : resolvedYear((int) $date->format('Y'), $calendar, $rubricSystem);

    return DayContract::from(
        $year->day($date),
        $year->provenance(),
        (new CalendarCatalog())->descriptor($calendar)
    )->toArray();
}

/**
 * The output contract for a civil date with its show-your-work resolution trace
 * (#233) filled in: the `resolution` slot explains why the office was chosen, what
 * became of every office it beat, and how the day's commemoration limit was reached,
 * each step cited to the governing rubric. This is what the API's `?explain` surface
 * calls. A convenience for {@see contract()} with explaining on.
 *
 * @return array<string, mixed>
 */
function explain(DateTimeImmutable $date, ?string $calendar = null, ?string $rubricSystem = null): array
{
    return contract($date, true, $calendar, $rubricSystem);
}

/**
 * The resolved civil year, memoised for the life of the process.
 *
 * Shared by {@see day()} and {@see contract()} so a (year, calendar, rubric-system)
 * triple is resolved at most once regardless of which entry point is called.
 *
 * @internal Not part of the public contract; the stable API is day()/contract().
 */
function resolvedYear(int $year, ?string $calendar = null, ?string $rubricSystem = null): ResolvedYear
{
    /** @var array<string, ResolvedYear> $resolved */
    static $resolved = [];

    $key = $year . '|' . ($calendar ?? '') . '|' . ($rubricSystem ?? '');
    if (!isset($resolved[$key])) {
        $resolved[$key] = (new CalendarCatalog())->resolver($calendar, $rubricSystem)->resolveYear($year);
    }

    return $resolved[$key];
}

/**
 * The resolved civil year with resolution tracing on, memoised separately from
 * {@see resolvedYear()} so explaining a day never perturbs the default resolution
 * (or the golden digest that hashes it).
 *
 * @internal Not part of the public contract; the stable API is explain()/contract().
 */
function explainedYear(int $year, ?string $calendar = null, ?string $rubricSystem = null): ResolvedYear
{
    /** @var array<string, ResolvedYear> $explained */
    static $explained = [];

    $key = $year . '|' . ($calendar ?? '') . '|' . ($rubricSystem ?? '');
    if (!isset($explained[$key])) {
        $explained[$key] = (new CalendarCatalog())
            ->resolver($calendar, $rubricSystem)
            ->explaining()
            ->resolveYear($year);
    }

    return $explained[$key];
}
