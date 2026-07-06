<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use Directorium\Core\Contract\DayContract;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\TemporalCalendar;
use RuntimeException;

/**
 * The engine-under-the-SSPX-overlay ↔ SSPX conformance gate (#45 / #49 / #80).
 *
 * The SSPX 1962 ordo ({@see fixtures/sspx}) is the authority for the SSPX *particular*
 * calendar: the universal 1962 base plus the Society's own elevations (St Pius X and
 * the Seven Sorrows to first class, tagged "(FSSPX)" upstream). This harness resolves
 * the engine **under the SSPX overlay** (#76/#78, via {@see CalendarCatalog}) and
 * compares its per-day class to the ordo. Its two jobs:
 *
 *   1. **Conformance.** Every FSSPX-tagged particular the ordo publishes must match the
 *      engine under the overlay — the overlay reproduces the Society's proper calendar
 *      exactly. {@see SspxOracleTest::testOverlayModelsEverySspxParticular()} asserts
 *      no `particular:true` day differs; a newly-published proper feast, or a broken
 *      overlay, fails it.
 *   2. **Tracked residual.** The differences that remain are all `particular:false`:
 *      base-1962 ranks the base engine still gets wrong, corroborated by missalemeum
 *      (the September Ember week #439, the Ascension vigil #440, n. 33 #441; also
 *      feeding #428), plus two SSPX divergences the fixed-date overlay does not model —
 *      the movable Seven Sorrows (Friday after Passion Sunday, an Easter-relative
 *      office beyond a SanctoralData overlay) and the Vigil of the Assumption (third
 *      class upstream against a second-class vigil under the 1960 Code of Rubrics
 *      n. 91, treated as a feed artifact, not conformed to). These are frozen,
 *      categorised, into the baseline ({@see baselinePath()}) and guarded for drift.
 *
 * The feed exposes only the office's class (I..IV) — not colour or a commemoration
 * count — so only class is compared (a documented allowance), and days whose class is
 * absent upstream are skipped.
 *
 * @see SspxOracleTest
 */
final class SspxOracle
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/sspx';
    private const MISSALEMEUM_DIR = __DIR__ . '/fixtures/missalemeum';

    /** The particular-calendar overlay the engine resolves under here. */
    private const CALENDAR = 'sspx';

    public static function baselinePath(): string
    {
        return self::FIXTURE_DIR . '/sspx-differences.ndjson';
    }

    /**
     * The fixture's committed year range, read from its provenance.
     *
     * @return list<int>
     */
    public static function years(): array
    {
        $provenance = json_decode(self::read(self::FIXTURE_DIR . '/provenance.json'), true, 512, JSON_THROW_ON_ERROR);
        $range = $provenance['range'];

        return range((int) $range['firstYear'], (int) $range['lastYear']);
    }

    /**
     * Every engine-under-overlay ↔ SSPX class mismatch across the fixture, ascending by
     * date. Each is a self-describing row carrying both other witnesses (the engine's
     * class under the SSPX overlay and missalemeum's, where it has a reading) and a
     * category. A conformant FSSPX particular is not a mismatch and so never appears.
     *
     * @return list<array{date: string, name: string, sspx: int, engine: int, missalemeum: int|null, category: string}>
     */
    public static function divergences(): array
    {
        $engine = self::engine();

        $rows = [];
        foreach (self::years() as $year) {
            $fixture = self::loadFixture($year);
            $missalemeum = self::missalemeumClasses($year);
            $resolved = $engine->resolveYear($year);
            $provenance = $resolved->provenance();

            foreach ($fixture as $date => $sspx) {
                if ($sspx['klasse'] === null) {
                    continue;
                }
                [$y, $m, $d] = array_map('intval', explode('-', $date));
                $day = DayContract::from($resolved->day(TemporalCalendar::utcDate($y, $m, $d)), $provenance)->toArray();
                $celebration = $day['celebration'][0] ?? null;
                if ($celebration === null) {
                    throw new RuntimeException(sprintf('Engine produced no celebration for %s.', $date));
                }

                $engineRank = (int) $celebration['rankOrdinal'];
                if ($sspx['klasse'] === $engineRank) {
                    continue;
                }

                $mm = $missalemeum[$date] ?? null;
                $rows[] = [
                    'date' => $date,
                    'name' => $sspx['name'],
                    'sspx' => $sspx['klasse'],
                    'engine' => $engineRank,
                    'missalemeum' => $mm,
                    'category' => self::classify($sspx, $engineRank, $mm),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return $a['date'] <=> $b['date'];
        });

        return $rows;
    }

    /**
     * The engine resolving under the SSPX particular calendar — the base 1962 engine
     * with the SSPX overlay (#76) layered on, exactly as a consumer gets by selecting
     * that calendar (#78). This is what makes the harness a conformance gate rather
     * than a base-engine divergence tracker.
     */
    private static function engine(): DayResolver
    {
        return (new CalendarCatalog())->resolver(self::CALENDAR);
    }

    /** The baseline text: one JSON mismatch per line, ascending, trailing newline. */
    public static function freezeText(): string
    {
        $out = '';
        foreach (self::divergences() as $row) {
            $out .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        }

        return $out;
    }

    /**
     * A deterministic taxonomy that turns each residual class mismatch into a worklist
     * item. Under the overlay a `sspx-particular` row is a conformance gap (the test
     * fails on it); the other categories are the tracked residual — corroborated
     * base-rank issues (#428) and the unmodelled SSPX divergences.
     *
     * @param array{name: string, klasse: int|null, particular: bool, feast: bool} $sspx
     */
    private static function classify(array $sspx, int $engineRank, ?int $missalemeum): string
    {
        if ($sspx['particular']) {
            // Upstream flags the day "(FSSPX)": an SSPX proper observance the overlay is
            // meant to reproduce. Appearing here means the overlay did NOT model it — a
            // conformance gap (see SspxOracleTest::testOverlayModelsEverySspxParticular).
            return 'sspx-particular';
        }
        if ($missalemeum !== null && $missalemeum === $sspx['klasse'] && $missalemeum !== $engineRank) {
            // Both independent sources agree against the engine — a base-1962 rank the
            // base engine gets wrong (the overlay does not touch it), corroborated (#428).
            return 'corroborates-base-rank';
        }
        if ($missalemeum === $engineRank && $missalemeum !== $sspx['klasse']) {
            // The engine (under the overlay) matches the base authority; SSPX stands
            // alone — a particular divergence the fixed-date overlay does not model
            // (the movable Seven Sorrows, the Assumption-vigil feed reading).
            return 'sspx-diverges-from-base';
        }

        // All three disagree, or missalemeum has no reading here: needs adjudication.
        return 'sspx-divergent-other';
    }

    /**
     * @return array<string, array{name: string, klasse: int|null, particular: bool, feast: bool}>
     */
    private static function loadFixture(int $year): array
    {
        $path = self::FIXTURE_DIR . '/' . $year . '.ndjson';
        $out = [];
        foreach (explode("\n", trim(self::read($path))) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array{date: string, name: string, klasse: int|null, particular: bool, feast: bool} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[$row['date']] = [
                'name' => $row['name'],
                'klasse' => $row['klasse'],
                'particular' => $row['particular'],
                'feast' => $row['feast'],
            ];
        }

        return $out;
    }

    /**
     * The missalemeum office class per date for the year, used as the second witness.
     * Returns an empty map when that fixture does not cover the year.
     *
     * @return array<string, int>
     */
    private static function missalemeumClasses(int $year): array
    {
        $path = self::MISSALEMEUM_DIR . '/' . $year . '.ndjson';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        $out = [];
        foreach (explode("\n", trim($contents)) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array{date: string, rank: int} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[$row['date']] = (int) $row['rank'];
        }

        return $out;
    }

    private static function read(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Oracle fixture not found: %s. Harvest it with tools/oracles.', $path));
        }

        return $contents;
    }
}
