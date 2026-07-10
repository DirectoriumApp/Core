<?php

/**
 * Resolution benchmark (#95 / #96).
 *
 * Measures the four paths the Api exercises at volume and checks each against a
 * documented budget (see docs/performance.md):
 *
 *   cold-day   a single day resolved in a fresh process — pays the corpus parse and
 *              the full-year build once (the first request a worker serves);
 *   warm-day   a day looked up from an already-resolved year (what a cache hit costs);
 *   full-year  every day of a year resolved and serialised;
 *   year-range consecutive years resolved with the corpus parsed once — proves the
 *              process-wide corpus cache is reused (no reparse per year).
 *
 * Timing only; it never changes resolved output. Run: `php bin/benchmark.php`.
 * A path over budget prints OVER and the script exits non-zero, so it can gate a
 * manual perf check; it is not wired into CI, where shared-runner timing is noisy.
 */

// phpcs:disable PSR1.Files.SideEffects -- a CLI benchmark necessarily declares its
// helpers and runs them in the same file.

declare(strict_types=1);

use Directorium\Core\Contract\DayContract;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Temporal\TemporalCalendar;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Documented budgets, in milliseconds, with generous headroom for slow runners. */
const BUDGET_MS = [
    'cold-day' => 1500.0,
    'warm-day' => 5.0,
    'full-year' => 4000.0,
    'year-range/year' => 1500.0,
];

const YEAR = 2024; // a leap year — 366 days
const ITERATIONS = 5;
const RANGE_YEARS = 5;

/** @return array<string, mixed> one day resolved end-to-end (year build + day + contract). */
function resolveDay(int $year, int $month, int $day): array
{
    $resolved = (new CalendarCatalog())->resolver(null, null)->resolveYear($year);
    $date = TemporalCalendar::utcDate($year, $month, $day);

    return DayContract::from($resolved->day($date), $resolved->provenance())->toArray();
}

/** Resolve and serialise every day of a year; returns the day count. */
function resolveFullYear(int $year): int
{
    $resolved = (new CalendarCatalog())->resolver(null, null)->resolveYear($year);
    $provenance = $resolved->provenance();
    $date = TemporalCalendar::utcDate($year, 1, 1);
    $end = TemporalCalendar::utcDate($year, 12, 31);
    $oneDay = new DateInterval('P1D');

    $count = 0;
    while ($date <= $end) {
        DayContract::from($resolved->day($date), $provenance)->toArray();
        $date = $date->add($oneDay);
        $count++;
    }

    return $count;
}

/**
 * @param callable():void $body
 *
 * @return float median elapsed milliseconds over ITERATIONS runs
 */
function timeMedian(callable $body): float
{
    $samples = [];
    for ($i = 0; $i < ITERATIONS; $i++) {
        $t0 = hrtime(true);
        $body();
        $samples[] = (hrtime(true) - $t0) / 1e6;
    }
    sort($samples);
    $mid = intdiv(count($samples), 2);

    return count($samples) % 2 === 1
        ? $samples[$mid]
        : ($samples[$mid - 1] + $samples[$mid]) / 2;
}

$results = [];

// cold-day: flush the corpus cache each run so the parse + year build are paid fresh.
$results['cold-day'] = timeMedian(static function (): void {
    Corpus::flush();
    resolveDay(YEAR, 9, 3);
});

// warm-day: resolve the year once, then time repeated day lookups on it.
$warmResolved = (new CalendarCatalog())->resolver(null, null)->resolveYear(YEAR);
$warmProvenance = $warmResolved->provenance();
$results['warm-day'] = timeMedian(static function () use ($warmResolved, $warmProvenance): void {
    for ($m = 1; $m <= 12; $m++) {
        $date = TemporalCalendar::utcDate(YEAR, $m, 15);
        DayContract::from($warmResolved->day($date), $warmProvenance)->toArray();
    }
}) / 12.0; // per-day

// full-year: cold build + serialise every day.
$dayCount = 0;
$results['full-year'] = timeMedian(static function () use (&$dayCount): void {
    Corpus::flush();
    $dayCount = resolveFullYear(YEAR);
});

// year-range: parse the corpus once, then resolve consecutive years; report per-year.
Corpus::flush();
$rangeTotal = timeMedian(static function (): void {
    for ($y = YEAR; $y < YEAR + RANGE_YEARS; $y++) {
        resolveFullYear($y);
    }
});
$results['year-range/year'] = $rangeTotal / RANGE_YEARS;

// Report.
printf("Resolution benchmark — %d iterations (median ms), year %d (%d days)\n\n", ITERATIONS, YEAR, $dayCount);
printf("  %-18s %12s %12s   %s\n", 'path', 'median ms', 'budget ms', 'status');
printf("  %s\n", str_repeat('-', 58));

$overBudget = false;
foreach ($results as $path => $ms) {
    $budget = BUDGET_MS[$path];
    $ok = $ms <= $budget;
    $overBudget = $overBudget || !$ok;
    printf("  %-18s %12.3f %12.1f   %s\n", $path, $ms, $budget, $ok ? 'ok' : 'OVER');
}

echo "\n";
exit($overBudget ? 1 : 0);
