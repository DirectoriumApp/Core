<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use Directorium\Core\Contract\DayContract;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\TemporalCalendar;
use RuntimeException;

/**
 * The engine ↔ missalemeum comparison for the validation harness (#45 / #48).
 *
 * missalemeum is the primary independent oracle for the base 1962 edition. For
 * every day in the pinned fixture ({@see fixtures/missalemeum}) this compares three
 * robust, edition-neutral facts of the day's principal office:
 *
 *   - **class** — the celebration's rank, 1 (highest) … 4;
 *   - **colour** — its liturgical colour(s), as an order-free set;
 *   - **commemorations** — how many the day carries.
 *
 * The English *title* is deliberately not compared: our engine resolves Latin
 * identities, missalemeum emits English display strings, and a title crosswalk
 * would re-encode the oracle rather than test against it. Class + colour together
 * pin *which* office won; the commemoration count pins the occurrence's tail.
 *
 * The two systems still legitimately differ on a known, tracked set of days (corpus
 * ranks awaiting citation, Sunday/feast commemoration rules, octave-tide colours).
 * Rather than assert zero divergence — which would demand the whole accuracy
 * campaign at once — this freezes the *current* divergence set as a categorised
 * baseline ({@see baselinePath()}). The test is green when the live divergences
 * equal the baseline exactly, so a regression (a new divergence) and an improvement
 * (a baselined divergence that disappears) both fail until reviewed — the harness
 * becomes a living worklist, not a rubber stamp.
 *
 * @see MissalemeumOracleTest
 */
final class MissalemeumOracle
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/missalemeum';

    /** Upstream colour codes → our colour vocabulary. */
    private const COLOUR = [
        'w' => 'white',
        'r' => 'red',
        'g' => 'green',
        'v' => 'violet',
        'b' => 'black',
        'p' => 'rose',
    ];

    public static function baselinePath(): string
    {
        return self::FIXTURE_DIR . '/known-differences.ndjson';
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
     * Every engine ↔ oracle divergence across the fixture, ascending by (date,
     * field). Each is a self-describing, categorised row.
     *
     * @return list<array{date: string, field: string, oracle: string, engine: string, category: string}>
     */
    public static function divergences(): array
    {
        $rows = [];
        foreach (self::years() as $year) {
            $fixture = self::loadFixture($year);
            $resolved = DayResolver::for1962()->resolveYear($year);
            $provenance = $resolved->provenance();

            foreach ($fixture as $date => $oracle) {
                [$y, $m, $d] = array_map('intval', explode('-', $date));
                $day = DayContract::from($resolved->day(TemporalCalendar::utcDate($y, $m, $d)), $provenance)->toArray();
                $celebration = $day['celebration'][0] ?? null;
                if ($celebration === null) {
                    throw new RuntimeException(sprintf('Engine produced no celebration for %s.', $date));
                }

                foreach (self::compareDay($date, $oracle, $day, $celebration) as $row) {
                    $rows[] = $row;
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['date'], $a['field']] <=> [$b['date'], $b['field']];
        });

        return $rows;
    }

    /** The baseline text: one JSON divergence per line, ascending, trailing newline. */
    public static function freezeText(): string
    {
        $out = '';
        foreach (self::divergences() as $row) {
            $out .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        }

        return $out;
    }

    /**
     * @param array{rank: int, colors: list<string>, commemorations: int} $oracle
     * @param array<string, mixed> $day
     * @param array<string, mixed> $celebration
     *
     * @return list<array{date: string, field: string, oracle: string, engine: string, category: string}>
     */
    private static function compareDay(string $date, array $oracle, array $day, array $celebration): array
    {
        $rows = [];

        $engineRank = (int) $celebration['rankOrdinal'];
        if ($oracle['rank'] !== $engineRank) {
            $rows[] = self::row($date, 'class', (string) $oracle['rank'], (string) $engineRank, $day, $celebration);
        }

        $oracleColour = self::oracleColours($oracle['colors']);
        $engineColour = self::engineColours($celebration['colour']);
        if ($oracleColour !== $engineColour) {
            $rows[] = self::row(
                $date,
                'colour',
                implode('+', $oracleColour),
                implode('+', $engineColour),
                $day,
                $celebration
            );
        }

        $engineCommem = count($day['commemoration']);
        if ($oracle['commemorations'] !== $engineCommem) {
            $rows[] = self::row(
                $date,
                'commemorations',
                (string) $oracle['commemorations'],
                (string) $engineCommem,
                $day,
                $celebration
            );
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $day
     * @param array<string, mixed> $celebration
     *
     * @return array{date: string, field: string, oracle: string, engine: string, category: string}
     */
    private static function row(
        string $date,
        string $field,
        string $oracle,
        string $engine,
        array $day,
        array $celebration
    ): array {
        return [
            'date' => $date,
            'field' => $field,
            'oracle' => $oracle,
            'engine' => $engine,
            'category' => self::classify($field, $oracle, $engine, $day, $celebration),
        ];
    }

    /**
     * A coarse, deterministic taxonomy so the baseline reads as a worklist. Each
     * category is a tracked class of known difference, not a claim of correctness.
     *
     * @param array<string, mixed> $day
     * @param array<string, mixed> $celebration
     */
    private static function classify(
        string $field,
        string $oracle,
        string $engine,
        array $day,
        array $celebration
    ): string {
        if ($field === 'class') {
            // Oracle ranks the office higher (a smaller number) than our corpus does,
            // or lower — almost always a sanctoral rank awaiting a cited correction.
            return (int) $oracle < (int) $engine ? 'corpus-rank-underranked' : 'corpus-rank-overranked';
        }

        if ($field === 'colour') {
            $season = $day['season'];
            if ($season === 'christmastide' || $season === 'epiphany') {
                return 'colour-christmas-epiphany-tide';
            }
            if (strpos($oracle, 'violet') !== false && strpos($engine, 'violet') !== false) {
                // Both call it violet but disagree on a second (red/black) colour —
                // the Palm Sunday / Good Friday two-colour convention.
                return 'colour-penitential-second';
            }

            return 'colour-other';
        }

        // Commemoration-count differences, bucketed by what our engine celebrates.
        $kind = $celebration['kind'];
        if ($kind === 'sunday') {
            return 'commem-sunday';
        }
        if ($kind === 'feast') {
            return 'commem-feast';
        }
        if ($kind === 'feria') {
            return 'commem-feria';
        }

        return 'commem-other';
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string> the mapped colour words, sorted, duplicates removed
     */
    private static function oracleColours(array $codes): array
    {
        $words = [];
        foreach ($codes as $code) {
            if (!isset(self::COLOUR[$code])) {
                throw new RuntimeException(sprintf('Unknown oracle colour code "%s".', $code));
            }
            $words[self::COLOUR[$code]] = true;
        }
        $words = array_keys($words);
        sort($words);

        return $words;
    }

    /**
     * @param array{base: string, roseAllowed: bool} $colour
     *
     * @return list<string>
     */
    private static function engineColours(array $colour): array
    {
        $words = [$colour['base']];
        if ($colour['roseAllowed']) {
            $words[] = 'rose';
        }
        sort($words);

        return $words;
    }

    /**
     * @return array<string, array{rank: int, colors: list<string>, commemorations: int}>
     */
    private static function loadFixture(int $year): array
    {
        $path = self::FIXTURE_DIR . '/' . $year . '.ndjson';
        $out = [];
        foreach (explode("\n", trim(self::read($path))) as $line) {
            if ($line === '') {
                continue;
            }
            /** @var array{date: string, rank: int, colors: list<string>, commemorations: int} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[$row['date']] = [
                'rank' => $row['rank'],
                'colors' => $row['colors'],
                'commemorations' => $row['commemorations'],
            ];
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
