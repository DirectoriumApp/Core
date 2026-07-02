<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Validation;

use Introibo\Core\Contract\DayContract;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Temporal\TemporalCalendar;
use RuntimeException;

/**
 * The engine ↔ SSPX comparison for the validation harness (#45 / #49).
 *
 * The SSPX 1962 ordo ({@see fixtures/sspx}) is a *second, independent* witness to the
 * 1962 calendar. It is deliberately NOT the authority on the base edition —
 * missalemeum is ({@see MissalemeumOracle}) — because SSPX keeps a *particular*
 * calendar: the universal 1962 base plus its own observances (St Pius X and the Seven
 * Sorrows elevated to first class, tagged "(FSSPX)" upstream). Its value is twofold:
 *
 *   1. **Corroboration.** Where SSPX and missalemeum agree on a class the engine does
 *      not, two independent sources indict the same base-1962 rank — stronger evidence
 *      than either alone, and material for the accuracy worklist (#428).
 *   2. **Overlay discovery.** Where SSPX alone differs, the difference is a candidate
 *      entry for the v0.2 SSPX particular-calendar overlay — the R2 priority.
 *
 * The feed exposes the office's class (I..IV); it does not expose colour or a
 * commemoration count, so only class is compared (a documented allowance), and days
 * whose class is absent upstream are skipped. Each class mismatch is frozen into a
 * categorised baseline ({@see baselinePath()}) exactly like the missalemeum harness:
 * the test is green when the live mismatches equal it, so a regression (a new
 * difference) and a drift (a baselined difference that changes) both fail until
 * reviewed. Because SSPX is a particular calendar every mismatch here is *expected* —
 * the baseline is a living catalogue of how the base engine relates to SSPX, not a
 * bug list.
 *
 * @see SspxOracleTest
 */
final class SspxOracle
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/sspx';
    private const MISSALEMEUM_DIR = __DIR__ . '/fixtures/missalemeum';

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
     * Every engine ↔ SSPX class mismatch across the fixture, ascending by date. Each
     * is a self-describing row carrying both other witnesses (the engine's class and
     * missalemeum's, where it has a reading) and a category.
     *
     * @return list<array{date: string, name: string, sspx: int, engine: int, missalemeum: int|null, category: string}>
     */
    public static function divergences(): array
    {
        $rows = [];
        foreach (self::years() as $year) {
            $fixture = self::loadFixture($year);
            $missalemeum = self::missalemeumClasses($year);
            $resolved = DayResolver::for1962()->resolveYear($year);
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
     * A deterministic taxonomy that turns each class mismatch into a worklist item.
     * The overlay categories seed the v0.2 SSPX overlay; the corroboration category
     * seeds the base-1962 accuracy worklist (#428).
     *
     * @param array{name: string, klasse: int|null, particular: bool, feast: bool} $sspx
     */
    private static function classify(array $sspx, int $engineRank, ?int $missalemeum): string
    {
        if ($sspx['particular']) {
            // Upstream flags the day "(FSSPX)": a definite SSPX particular observance.
            return 'sspx-particular';
        }
        if ($missalemeum !== null && $missalemeum === $sspx['klasse'] && $missalemeum !== $engineRank) {
            // Both independent sources agree against the engine — a base-1962 rank the
            // engine gets wrong, corroborated (see #428).
            return 'corroborates-base-rank';
        }
        if ($missalemeum === $engineRank && $missalemeum !== $sspx['klasse']) {
            // The engine matches the base authority; SSPX stands alone — a particular
            // difference to model in the overlay.
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
