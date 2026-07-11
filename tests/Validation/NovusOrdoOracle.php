<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Contract\Provenance;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use Directorium\Core\Temporal\TemporalCalendar;
use RuntimeException;

/**
 * The engine ↔ Novus-Ordo cross-check for the validation harness (#45 / #260).
 *
 * The reformed General Roman Calendar (roman:novus-ordo-2002) is proved against TWO
 * independent, open-source calendar engines — the ≥2-oracle standard (roadmap v3):
 *
 *   - **LitCal** (Apache-2.0, PHP) — a whole-year API; each event carries a numeric
 *     grade (0 weekday … 6 solemnity, 7 higher) and colour(s);
 *   - **calapi / In Adiutorium** (LGPL-3.0-or-later, Ruby) — a per-day API; each celebration carries
 *     a rank string and colour, listing the day's optional memorials separately from
 *     the obligatory/ferial principal.
 *
 * Both are **run-and-compare oracles only**, never a data source (clean-room): the
 * committed fixtures ({@see fixtures/novus-ordo}) are harvested offline by
 * tools/oracles/harvest-novus-ordo.mjs and read here without a network call.
 *
 * The two engines and ours all use different rank vocabularies, so — as with the
 * missalemeum arm — comparison is on robust, edition-neutral facts, normalised to a
 * coarse liturgical grade the three share:
 *
 *     solemnity  >  feast  >  memorial  >  feria
 *
 * For every day it compares the principal office's **grade** and (on a matching,
 * non-ferial grade) its **colour**. A Novus-Ordo weekday whose only offices are optional
 * memorials resolves to the feria (the memorials are electable — #108); LitCal surfaces
 * the memorial as the day's highest-grade event, so it normalises to `feria` too, and
 * the two agree. The English/Latin *title* is never compared (it would re-encode the
 * oracle rather than test against it).
 *
 * The engine and the two oracles still legitimately differ on a small, tracked set of
 * days — the reform's post-2002 decrees our 2002 typical-edition baseline excludes (the
 * 2018 Mary Mother of the Church, the 2016 raising of Mary Magdalene to a feast, …; see
 * #366), the movable Immaculate Heart we defer (KNOWN-LIMITATIONS), All Souls' sui
 * generis grade, and a handful of days where the two oracles disagree with *each other*
 * and our engine matches the correct one. Rather than assert zero divergence, this
 * freezes the current divergence set as a categorised baseline ({@see baselinePath()});
 * the test is green when the live divergences equal it exactly, so a regression and an
 * improvement both fail until reviewed — a living worklist, not a rubber stamp.
 *
 * @see NovusOrdoOracleTest
 * @see fixtures/novus-ordo/README.md
 *
 * @phpstan-type Row array{date:string,oracle:string,field:string,engine:string,source:string,category:string}
 * @phpstan-type EngineDay array{grade:string,colour:string,name:string}
 */
final class NovusOrdoOracle
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/novus-ordo';

    /** Ferial temporal kinds: a rankOrdinal-3 office of one of these is a privileged weekday, not a memorial. */
    private const FERIAL_KINDS = ['feria', 'within-octave', 'vigil', 'ember-day', 'rogation-day'];

    /** The coarse grade ladder, most to least. */
    private const GRADE_ORDER = ['solemnity' => 4, 'feast' => 3, 'memorial' => 2, 'feria' => 1];

    /** calapi rank string → coarse grade. */
    private const CALAPI_GRADE = [
        'Easter triduum' => 'solemnity',
        'Primary liturgical days' => 'solemnity',
        'solemnity' => 'solemnity',
        'feast of the Lord' => 'feast',
        'Sunday' => 'feast',
        'feast' => 'feast',
        'memorial' => 'memorial',
        'optional memorial' => 'feria',
        'commemoration' => 'feria',
        'ferial' => 'feria',
    ];

    /** Oracle colour vocabulary → ours (LitCal writes "purple" for Lenten violet). */
    private const COLOUR = [
        'purple' => 'violet',
        'pink' => 'rose',
    ];

    public static function baselinePath(): string
    {
        return self::FIXTURE_DIR . '/known-differences.ndjson';
    }

    /**
     * The fixtures' committed year range, read from their provenance.
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
     * Every engine ↔ oracle divergence across both fixtures, ascending by (date, oracle,
     * field). Each is a self-describing, categorised row.
     *
     * @return list<Row>
     */
    public static function divergences(): array
    {
        $rows = [];
        foreach (self::years() as $year) {
            $resolved = DayResolver::forEdition(RubricSystem::fromString('novus-ordo'))->resolveYear($year);
            $engine = self::engineYear($resolved, $resolved->provenance(), $year);

            foreach (self::loadLitCal($year) as $date => $o) {
                $grade = self::litGrade($o['grade']);
                // LitCal's feria-day principal is the highest-grade event (the optional
                // memorial), not the ferial — so its colour is never comparable on a feria.
                foreach (self::compareDay('litcal', $date, $grade, $o['colours'], false, $engine) as $row) {
                    $rows[] = $row;
                }
            }
            foreach (self::loadCalApi($year) as $date => $o) {
                $onFeria = $o['ferialPrincipal'];
                foreach (self::compareDay('calapi', $date, $o['grade'], $o['colours'], $onFeria, $engine) as $row) {
                    $rows[] = $row;
                }
            }
        }

        // (date, oracle, field) is a unique key — compareDay emits at most one row per
        // (oracle, date) (a grade mismatch returns before the colour check), and the fixtures
        // are date-keyed — so the sort is total and freezeText() is byte-reproducible across
        // PHP versions (the pre-8.0 unstable / 8.0+ stable usort distinction never bites).
        usort($rows, static function (array $a, array $b): int {
            return [$a['date'], $a['oracle'], $a['field']] <=> [$b['date'], $b['oracle'], $b['field']];
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
     * Compare one oracle's day against the engine: grade first, then colour.
     *
     * Colour is compared on a matching non-ferial grade always, and on a ferial grade only
     * when the oracle's principal is itself the ferial ($colourValidOnFeria) — so we compare
     * the day's own colour, not a lapsed optional memorial's. calapi's `celebrations[0]` on a
     * feria day IS the ferial, so its colour guards the season colour of the ~half of days
     * that are plain feriae; LitCal's principal there is the highest-grade event (the optional
     * memorial), a different office, so its feria colour is not comparable.
     *
     * @param 'litcal'|'calapi'        $oracle
     * @param list<string>             $oracleColours the oracle's colour word(s) for the day
     * @param array<string, EngineDay> $engine
     *
     * @return list<Row>
     */
    private static function compareDay(
        string $oracle,
        string $date,
        string $oracleGrade,
        array $oracleColours,
        bool $colourValidOnFeria,
        array $engine
    ): array {
        $day = $engine[$date] ?? null;
        if ($day === null) {
            throw new RuntimeException(sprintf('Engine produced no Novus-Ordo day for %s.', $date));
        }

        if ($oracleGrade !== $day['grade']) {
            $category = self::GRADE_ORDER[$oracleGrade] > self::GRADE_ORDER[$day['grade']]
                ? 'grade-oracle-higher'
                : 'grade-core-higher';

            return [self::row($date, $oracle, 'grade', $day['grade'], $oracleGrade, $category)];
        }

        if ($day['grade'] === 'feria' && !$colourValidOnFeria) {
            return [];
        }

        $colours = array_map(static fn (string $c): string => self::COLOUR[$c] ?? $c, $oracleColours);
        if (!in_array($day['colour'], $colours, true)) {
            return [self::row($date, $oracle, 'colour', $day['colour'], implode('+', $colours), 'colour')];
        }

        return [];
    }

    /**
     * @return Row
     */
    private static function row(
        string $date,
        string $oracle,
        string $field,
        string $engine,
        string $source,
        string $category
    ): array {
        return [
            'date' => $date,
            'oracle' => $oracle,
            'field' => $field,
            'engine' => $engine,
            'source' => $source,
            'category' => $category,
        ];
    }

    /**
     * Resolve the whole Novus-Ordo year to the comparable per-day facts: the principal
     * office's coarse grade and base colour.
     *
     * @return array<string, EngineDay>
     */
    private static function engineYear(ResolvedYear $resolved, Provenance $provenance, int $year): array
    {
        $out = [];
        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        $oneDay = new DateInterval('P1D');

        while ($date <= $end) {
            $day = DayContract::from($resolved->day($date), $provenance)->toArray();
            $celebration = $day['celebration'][0] ?? null;
            if ($celebration === null) {
                throw new RuntimeException(sprintf('Engine produced no celebration for %s.', $date->format('Y-m-d')));
            }
            $out[$date->format('Y-m-d')] = [
                'grade' => self::engineGrade((string) $celebration['kind'], (int) $celebration['rankOrdinal']),
                'colour' => (string) $celebration['colour']['base'],
                'name' => (string) ($celebration['names']['la'] ?? ''),
            ];
            $date = $date->add($oneDay);
        }

        return $out;
    }

    /**
     * Our RankClass ordinal + kind → the coarse grade. A privileged temporal day carries
     * a ferial *kind* but tops the Table of Liturgical Days (the Triduum, Ash Wednesday,
     * the first days of Holy Week, an octave), so a ferial-kind office of class I–II is
     * a solemnity-tier celebration; a class-III ferial-kind office is a privileged
     * weekday (Lent/Advent) — a feria, not a memorial.
     */
    private static function engineGrade(string $kind, int $rankOrdinal): string
    {
        if (in_array($kind, self::FERIAL_KINDS, true) && $rankOrdinal <= 2) {
            return 'solemnity';
        }
        if ($rankOrdinal === 1) {
            return 'solemnity';
        }
        if ($rankOrdinal === 2) {
            return 'feast';
        }
        if ($rankOrdinal === 3) {
            return in_array($kind, self::FERIAL_KINDS, true) ? 'feria' : 'memorial';
        }

        return 'feria';
    }

    /** LitCal numeric grade (0 weekday … 7 higher solemnity) → the coarse grade. */
    private static function litGrade(int $grade): string
    {
        // Fail closed on a grade outside the documented 0–7 domain (symmetry with the
        // calapi rank map), so an upstream schema drift is caught rather than silently
        // bucketed.
        if ($grade < 0 || $grade > 7) {
            throw new RuntimeException(sprintf('Unexpected LitCal grade "%d".', $grade));
        }
        if ($grade >= 6) {
            return 'solemnity';
        }
        if ($grade >= 4) {
            return 'feast';
        }
        if ($grade === 3) {
            return 'memorial';
        }

        return 'feria';
    }

    /**
     * @return array<string, array{grade: int, colours: list<string>, name: string}>
     */
    private static function loadLitCal(int $year): array
    {
        $out = [];
        foreach (self::lines(self::FIXTURE_DIR . '/litcal/' . $year . '.ndjson') as $line) {
            /** @var array{date: string, grade: int, colors: list<string>, name: string} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $out[$row['date']] = [
                'grade' => (int) $row['grade'],
                'colours' => $row['colors'],
                'name' => (string) $row['name'],
            ];
        }

        return $out;
    }

    /** calapi principal ranks that ARE the ferial office itself (so their colour is the day's). */
    private const CALAPI_FERIAL_RANKS = ['ferial', 'commemoration'];

    /**
     * calapi: the principal is `celebrations[0]` (the obligatory/ferial office); its rank
     * string maps to the coarse grade, its colour to the day's colour. `ferialPrincipal`
     * flags the days whose principal is the ferial itself, so its colour may be compared even
     * on a feria-tier day (unlike LitCal, whose feria-day principal is an optional memorial).
     *
     * @return array<string, array{grade: string, colours: list<string>, name: string, ferialPrincipal: bool}>
     */
    private static function loadCalApi(int $year): array
    {
        $out = [];
        foreach (self::lines(self::FIXTURE_DIR . '/calapi/' . $year . '.ndjson') as $line) {
            /** @var array{date: string, celebrations: list<array{title: string, colour: string, rank: string}>} $row */
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $principal = $row['celebrations'][0] ?? null;
            if ($principal === null) {
                throw new RuntimeException(sprintf('calapi has no celebration for %s.', $row['date']));
            }
            $rank = (string) $principal['rank'];
            if (!isset(self::CALAPI_GRADE[$rank])) {
                throw new RuntimeException(sprintf('Unknown calapi rank "%s" on %s.', $rank, $row['date']));
            }
            $out[$row['date']] = [
                'grade' => self::CALAPI_GRADE[$rank],
                'colours' => [(string) $principal['colour']],
                'name' => (string) $principal['title'],
                'ferialPrincipal' => in_array($rank, self::CALAPI_FERIAL_RANKS, true),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $path): array
    {
        $out = [];
        foreach (explode("\n", trim(self::read($path))) as $line) {
            if ($line !== '') {
                $out[] = $line;
            }
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
