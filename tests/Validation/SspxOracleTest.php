<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), oracle #3 — engine under the SSPX overlay vs the pinned
 * SSPX 1962 ordo (#49 / #80).
 *
 * This is the SSPX particular-calendar **conformance gate**: the engine resolves under
 * the SSPX overlay (#76/#78) and must reproduce every FSSPX-tagged particular the ordo
 * publishes ({@see testOverlayModelsEverySspxParticular}). The differences that remain
 * are all `particular:false` and tracked in a categorised baseline — corroborated
 * base-1962 rank issues (which also feed #428) and the two SSPX divergences the
 * fixed-date overlay does not model. See {@see SspxOracle} for the comparison shape and
 * the rationale for the baseline (approval-test) design.
 *
 * @group edition-1962
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
     * The conformance gate (#80): the engine under the SSPX overlay reproduces every
     * FSSPX-tagged particular the ordo publishes, so no `particular:true` day differs.
     * A broken overlay or a newly-published Society proper feast fails here, naming the
     * days — distinct from the tracked base-rank residual guarded below.
     */
    public function testOverlayModelsEverySspxParticular(): void
    {
        $unmodelled = array_values(array_filter(
            SspxOracle::divergences(),
            static fn (array $row): bool => $row['category'] === 'sspx-particular'
        ));

        $message = sprintf(
            "The SSPX overlay leaves %d FSSPX particular(s) unmodelled — the engine under "
            . "the overlay does not match the SSPX ordo on:\n  %s\n"
            . "Model them in tools/generator/facts/overlays/sspx.yaml and rebuild the corpus.",
            count($unmodelled),
            implode("\n  ", array_map(
                static fn (array $r): string => sprintf(
                    '%s %s (SSPX class %d, engine class %d)',
                    $r['date'],
                    $r['name'],
                    $r['sspx'],
                    $r['engine']
                ),
                $unmodelled
            ))
        );

        self::assertSame([], $unmodelled, $message);
    }

    /**
     * The residual after conformance: the live engine-under-overlay ↔ SSPX class
     * mismatches must equal the committed baseline exactly. A new mismatch (regression)
     * and a changed one (drift) both fail, naming the days, so no calendar change slips
     * through unreviewed.
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
