<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use Directorium\Core\Contract\DayContract;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), oracle #4 — the optional Divinum Officium cross-check (#50).
 *
 * DO is a heavyweight Perl oracle, so it is optional: the core suite must run without
 * it. These tests therefore verify, in CI, the two things that do not need DO — the
 * pure class parser and the graceful-skip behaviour — and run the live comparison only
 * when a DO checkout is configured (DIVINUM_OFFICIUM_PATH). See
 * {@see DivinumOfficiumOracle} for the design rationale.
 */
final class DivinumOfficiumOracleTest extends TestCase
{
    /**
     * The class parser is the only fragile part of the integration, so it is covered
     * directly against representative Divinum Officium office output. The snippets use
     * DO's real 1960 "N. classis" vocabulary (see web/www/horas/Latin/Sancti and DO's
     * office rendering); this proves the parser handles the forms it will meet.
     *
     * @dataProvider officeOutputs
     */
    public function testExtractClassReadsTheClassFromOfficeOutput(string $output, ?int $expected): void
    {
        self::assertSame($expected, DivinumOfficiumOracle::extractClass($output));
    }

    /**
     * @return array<string, array{0: string, 1: int|null}>
     */
    public static function officeOutputs(): array
    {
        return [
            'Nativity I classis' => ['In Nativitate Domini ~ I. classis', 1],
            'Sunday II classis' => ['Dominica VI Post Pentecosten ~ II. classis', 2],
            'St Pius X III classis' => ['S. Pii X Papæ Confessoris ~ III. classis', 3],
            'feria IV classis' => ['Feria V infra Hebdomadam VI ~ IV. classis', 4],
            'no period after numeral' => ['Sanctæ Mariæ Magdalenæ ~ III classis', 3],
            'lowercase and spacing' => ['something i.  Classis rubric', 1],
            'arabic fallback' => ['Rank line: 2. classis', 2],
            'longest numeral wins' => ['... III. classis ...', 3],
            'commemoration has no class' => ['Commemoratio: S. Silverii Papæ', null],
            'plain text without a class' => ['Feria Quinta — no rank marker here', null],
        ];
    }

    /**
     * When DO is not configured the oracle reports itself unavailable with an
     * actionable reason — the graceful-skip contract the core suite relies on. (In the
     * rare CI environment that does have DO configured this assertion is vacuously
     * skipped.)
     */
    public function testReportsUnavailableWithGuidanceWhenNotConfigured(): void
    {
        if (DivinumOfficiumOracle::isAvailable()) {
            self::assertTrue(true, 'Divinum Officium is configured; the live test covers it.');

            return;
        }

        self::assertFalse(DivinumOfficiumOracle::isAvailable());
        self::assertMatchesRegularExpression(
            '/DIVINUM_OFFICIUM_PATH|perl/',
            DivinumOfficiumOracle::unavailableReason()
        );
    }

    /**
     * The live cross-check: where DO is installed, its class for each sample day must
     * match the engine's. Skipped cleanly when DO is absent. If DO is present but the
     * parser reads no class for any day, that fails loudly — a signal to update
     * {@see DivinumOfficiumOracle::extractClass()} for the installed DO version, rather
     * than a silent pass.
     */
    public function testEngineAgreesWithDivinumOfficiumOnTheSample(): void
    {
        if (!DivinumOfficiumOracle::isAvailable()) {
            self::markTestSkipped(DivinumOfficiumOracle::unavailableReason());
        }

        $compared = 0;
        $mismatches = [];
        foreach (DivinumOfficiumOracle::contestedDates() as $date) {
            $doClass = DivinumOfficiumOracle::resolveClass($date);
            if ($doClass === null) {
                continue;
            }
            $compared++;
            $engineClass = self::engineClass($date);
            if ($doClass !== $engineClass) {
                $mismatches[] = sprintf('%s: DO=%d engine=%d', $date, $doClass, $engineClass);
            }
        }

        self::assertNotSame(
            0,
            $compared,
            'Divinum Officium is installed but no class was parsed from any sample day; '
            . 'update DivinumOfficiumOracle::extractClass() for this DO version.'
        );
        self::assertSame(
            [],
            $mismatches,
            "Engine ↔ Divinum Officium class mismatches:\n  " . implode("\n  ", $mismatches)
        );
    }

    private static function engineClass(string $date): int
    {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $resolved = DayResolver::for1962()->resolveYear($y);
        $day = DayContract::from(
            $resolved->day(TemporalCalendar::utcDate($y, $m, $d)),
            $resolved->provenance()
        )->toArray();

        return (int) $day['celebration'][0]['rankOrdinal'];
    }
}
