<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), the Novus-Ordo arm (#260) — the reformed General Roman
 * Calendar (roman:novus-ordo-2002) cross-checked against two independent open-source
 * engines, LitCal (PHP) and calapi (Ruby): the ≥2-oracle standard. See
 * {@see NovusOrdoOracle} for the normalisation, the comparison shape, and the rationale
 * for the baseline (approval-test) design, and fixtures/novus-ordo/README.md for the
 * day-by-day account of every tracked difference.
 *
 * @group edition-novus-ordo-2002
 */
final class NovusOrdoOracleTest extends TestCase
{
    /** The recognised known-difference categories — a new one means a mis-labelled row. */
    private const CATEGORIES = [
        'grade-oracle-higher',
        'grade-core-higher',
        'colour',
    ];

    /**
     * The heart of the arm: the live engine ↔ oracle divergences must equal the committed
     * baseline exactly. A new divergence (regression) and a vanished one (improvement)
     * both fail, naming the days, so no calendar change slips through unreviewed.
     */
    public function testLiveDivergencesEqualTheTrackedBaseline(): void
    {
        $live = array_values(array_filter(explode("\n", trim(NovusOrdoOracle::freezeText()))));
        $baseline = array_values(array_filter(explode("\n", trim($this->readBaseline()))));

        $regressions = array_values(array_diff($live, $baseline));
        $improvements = array_values(array_diff($baseline, $live));

        $message = sprintf(
            "Engine ↔ Novus-Ordo oracle divergences changed from the tracked baseline.\n"
            . "  %d new divergence(s) (regressions): %s\n"
            . "  %d baselined divergence(s) now resolved (improvements): %s\n"
            . "If this reflects a reviewed calendar change, run `php bin/freeze-novus-ordo-baseline.php` and commit.",
            count($regressions),
            self::sample($regressions),
            count($improvements),
            self::sample($improvements)
        );

        self::assertSame([], array_merge($regressions, $improvements), $message);
    }

    /**
     * The baseline is well-formed: every row carries the six fields, a recognised
     * category, and the file is sorted ascending by (date, oracle, field) with no
     * duplicates — so it stays a legible worklist.
     */
    public function testBaselineIsWellFormedAndSorted(): void
    {
        $lines = array_values(array_filter(explode("\n", trim($this->readBaseline()))));
        self::assertNotSame([], $lines, 'The known-differences baseline is empty; expected tracked rows.');

        $keys = [];
        foreach ($lines as $line) {
            /** @var array{date: string, oracle: string, field: string, engine: string, source: string, category: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            foreach (['date', 'oracle', 'field', 'engine', 'source', 'category'] as $field) {
                self::assertArrayHasKey($field, $row, "Baseline row missing '$field': $line");
            }
            self::assertContains($row['oracle'], ['litcal', 'calapi'], "Unknown oracle: {$row['oracle']}");
            self::assertContains($row['category'], self::CATEGORIES, "Unknown category: {$row['category']}");
            $keys[] = $row['date'] . '|' . $row['oracle'] . '|' . $row['field'];
        }

        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys, 'Baseline must be sorted ascending by (date, oracle, field).');
        self::assertSame(count($keys), count(array_unique($keys)), 'Baseline has duplicate rows.');
    }

    /**
     * A coarse floor confirming the agreement is real — the arm proves genuine agreement,
     * not a frozen shrug. Across every day of every fixture year, compared twice (once per
     * oracle), the divergences are a small fraction (~2%). The precise regression guard is
     * {@see testLiveDivergencesEqualTheTrackedBaseline} above, which fails on any change; this
     * is only the sanity floor that the two engines and ours have not silently drifted apart.
     */
    public function testEngineAgreesWithBothOraclesOnTheVastMajorityOfDays(): void
    {
        $days = 0;
        foreach (NovusOrdoOracle::years() as $year) {
            $days += (int) (new \DateTimeImmutable("$year-12-31"))->format('z') + 1;
        }
        $comparisons = $days * 2; // each day is compared against both oracles

        $divergences = count(NovusOrdoOracle::divergences());
        $agreement = 1 - $divergences / $comparisons;

        self::assertGreaterThan(
            0.97,
            $agreement,
            sprintf(
                'Novus-Ordo agreement with the two oracles fell to %.1f%% (%d/%d divergent).',
                $agreement * 100,
                $divergences,
                $comparisons
            )
        );
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
        $raw = file_get_contents(NovusOrdoOracle::baselinePath());
        self::assertIsString($raw, 'The known-differences baseline is missing.');

        return $raw;
    }
}
