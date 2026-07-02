<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Validation;

use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), oracle #3 — engine vs the pinned SSPX 1962 ordo (#49).
 *
 * SSPX is a *second, independent* witness to the 1962 calendar and a *particular*
 * calendar in its own right. This asserts the engine's per-day class matches SSPX
 * except for a tracked baseline of differences, categorised so the file doubles as a
 * worklist: corroboration of base-1962 rank issues (which also feed #428) and the
 * SSPX-particular differences that seed the v0.2 overlay. See {@see SspxOracle} for the
 * comparison shape and the rationale for the baseline (approval-test) design.
 */
final class SspxOracleTest extends TestCase
{
    /** The recognised difference categories — a new one means a mis-labelled row. */
    private const CATEGORIES = [
        'corroborates-base-rank',
        'sspx-particular',
        'sspx-diverges-from-base',
        'sspx-divergent-other',
    ];

    /**
     * The heart of the harness: the live engine ↔ SSPX class mismatches must equal the
     * committed baseline exactly. A new mismatch (regression) and a changed one (drift)
     * both fail, naming the days, so no calendar change slips through unreviewed.
     */
    public function testLiveDifferencesEqualTheTrackedBaseline(): void
    {
        $live = array_values(array_filter(explode("\n", trim(SspxOracle::freezeText()))));
        $baseline = array_values(array_filter(explode("\n", trim($this->readBaseline()))));

        $regressions = array_values(array_diff($live, $baseline));
        $drifts = array_values(array_diff($baseline, $live));

        $message = sprintf(
            "Engine ↔ SSPX class differences changed from the tracked baseline.\n"
            . "  %d new/changed difference(s): %s\n"
            . "  %d baselined difference(s) no longer produced: %s\n"
            . "If this reflects a reviewed calendar change, run `php bin/freeze-sspx-baseline.php` and commit.",
            count($regressions),
            self::sample($regressions),
            count($drifts),
            self::sample($drifts)
        );

        self::assertSame([], array_merge($regressions, $drifts), $message);
    }

    /**
     * The baseline is well-formed: every row carries the six fields, a recognised
     * category, and the file is sorted ascending by date with no duplicate days — so it
     * stays a legible worklist.
     */
    public function testBaselineIsWellFormedAndSorted(): void
    {
        $lines = array_values(array_filter(explode("\n", trim($this->readBaseline()))));
        self::assertNotSame([], $lines, 'The SSPX differences baseline is empty; expected tracked rows.');

        $dates = [];
        foreach ($lines as $line) {
            /** @var array{date: string, name: string, sspx: int, engine: int, missalemeum: int|null, category: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            foreach (['date', 'name', 'sspx', 'engine', 'missalemeum', 'category'] as $field) {
                self::assertArrayHasKey($field, $row, "Baseline row missing '$field': $line");
            }
            self::assertContains($row['category'], self::CATEGORIES, "Unknown category: {$row['category']}");
            self::assertNotSame($row['sspx'], $row['engine'], "Row is not a mismatch: $line");
            $dates[] = $row['date'];
        }

        $sorted = $dates;
        sort($sorted);
        self::assertSame($sorted, $dates, 'Baseline must be sorted ascending by date.');
        self::assertSame(count($dates), count(array_unique($dates)), 'Baseline has duplicate days.');
    }

    /**
     * @param list<string> $lines
     */
    private static function sample(array $lines): string
    {
        if ($lines === []) {
            return '(none)';
        }

        return implode('; ', array_slice($lines, 0, 5)) . (count($lines) > 5 ? ' …' : '');
    }

    private function readBaseline(): string
    {
        $raw = file_get_contents(SspxOracle::baselinePath());
        self::assertIsString($raw, 'The SSPX differences baseline is missing.');

        return $raw;
    }
}
