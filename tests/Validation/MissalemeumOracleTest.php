<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), oracle #2 — engine vs the pinned missalemeum fixture (#48).
 *
 * Asserts the engine's day resolution matches the independent missalemeum oracle
 * across the fixture, field by field (class, colour, commemoration count), except
 * for the tracked known-differences baseline. See {@see MissalemeumOracle} for the
 * comparison shape and the rationale for the baseline (approval-test) design.
 */
final class MissalemeumOracleTest extends TestCase
{
    /** The recognised known-difference categories — a new one means a mis-labelled row. */
    private const CATEGORIES = [
        'corpus-rank-underranked',
        'corpus-rank-overranked',
        'colour-christmas-epiphany-tide',
        'colour-penitential-second',
        'colour-other',
        'commem-sunday',
        'commem-feast',
        'commem-feria',
        'commem-other',
    ];

    /**
     * The heart of the harness: the live engine ↔ oracle divergences must equal the
     * committed baseline exactly. A new divergence (regression) and a vanished one
     * (improvement) both fail, naming the days, so no calendar change slips through
     * unreviewed.
     */
    public function testLiveDivergencesEqualTheTrackedBaseline(): void
    {
        $live = array_values(array_filter(explode("\n", trim(MissalemeumOracle::freezeText()))));
        $baseline = array_values(array_filter(explode("\n", trim($this->readBaseline()))));

        $regressions = array_values(array_diff($live, $baseline));
        $improvements = array_values(array_diff($baseline, $live));

        $message = sprintf(
            "Engine ↔ missalemeum divergences changed from the tracked baseline.\n"
            . "  %d new divergence(s) (regressions): %s\n"
            . "  %d baselined divergence(s) now resolved (improvements): %s\n"
            . "If this reflects a reviewed calendar change, run `php bin/freeze-oracle-baseline.php` and commit.",
            count($regressions),
            self::sample($regressions),
            count($improvements),
            self::sample($improvements)
        );

        self::assertSame([], array_merge($regressions, $improvements), $message);
    }

    /**
     * The baseline is well-formed: every row carries the five fields, a recognised
     * category, and the file is sorted ascending by (date, field) with no duplicates
     * — so it stays a legible worklist.
     */
    public function testBaselineIsWellFormedAndSorted(): void
    {
        $lines = array_values(array_filter(explode("\n", trim($this->readBaseline()))));
        self::assertNotSame([], $lines, 'The known-differences baseline is empty; expected tracked rows.');

        $keys = [];
        foreach ($lines as $line) {
            /** @var array{date: string, field: string, oracle: string, engine: string, category: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            foreach (['date', 'field', 'oracle', 'engine', 'category'] as $field) {
                self::assertArrayHasKey($field, $row, "Baseline row missing '$field': $line");
            }
            self::assertContains($row['category'], self::CATEGORIES, "Unknown category: {$row['category']}");
            $keys[] = $row['date'] . '|' . $row['field'];
        }

        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys, 'Baseline must be sorted ascending by (date, field).');
        self::assertSame(count($keys), count(array_unique($keys)), 'Baseline has duplicate (date, field) rows.');
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
        $raw = file_get_contents(MissalemeumOracle::baselinePath());
        self::assertIsString($raw, 'The known-differences baseline is missing.');

        return $raw;
    }
}
