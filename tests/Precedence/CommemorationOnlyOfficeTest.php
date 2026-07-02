<?php

declare(strict_types=1);

namespace Introibo\Core\Tests\Precedence;

use DateInterval;
use Introibo\Core\Contract\DayContract;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the commemoration-grade office bug surfaced by the
 * validation harness (#45): a IV-class commemoration was celebrated as the day's
 * office instead of the feria (#424).
 *
 * A commemoration has no proper office (1960 rubrics, nn. 106-114): the feria or
 * Sunday is celebrated and the saint is merely commemorated. The engine now awards
 * an observance of {@see \Introibo\Core\Observance\ObservanceKind::COMMEMORATION_ONLY}
 * the lowest precedence tier (below the whole Table of Liturgical Days), so it can
 * never win an occurrence.
 */
final class CommemorationOnlyOfficeTest extends TestCase
{
    /**
     * A commemoration-grade saint on an ordinary feria yields the office to the
     * feria and is itself commemorated — never celebrated in its stead.
     */
    public function testCommemorationGradeSaintYieldsToTheFeria(): void
    {
        // 20 June: St Silverius (commemoration-only) on a green feria of the time
        // after Pentecost.
        $resolved = DayResolver::for1962()->resolveYear(2024);
        $day = DayContract::from(
            $resolved->day(TemporalCalendar::utcDate(2024, 6, 20)),
            $resolved->provenance()
        )->toArray();

        $celebration = $day['celebration'][0] ?? null;
        self::assertNotNull($celebration);
        self::assertSame('feria', $celebration['kind'], 'The feria is the office, not the commemoration.');
        self::assertSame('green', $celebration['colour']['base'], 'The ferial colour, not the martyr’s red.');

        $commemorated = $day['commemoration'][0] ?? null;
        self::assertNotNull($commemorated, 'The saint must still be commemorated.');
        self::assertSame('commemoration-only', $commemorated['kind']);
        self::assertStringContainsString('Silverii', $commemorated['names']['la']);
        self::assertSame('commemorate', $commemorated['outcome']);
    }

    /**
     * The invariant behind the fix: across a full year, nothing of kind
     * commemoration-only is ever the day's celebration.
     */
    public function testNoCommemorationOnlyOfficeIsEverCelebrated(): void
    {
        $offenders = [];
        foreach ([2024, 2025, 2026] as $year) {
            $resolved = DayResolver::for1962()->resolveYear($year);
            $date = TemporalCalendar::utcDate($year, 1, 1);
            $end = TemporalCalendar::utcDate($year, 12, 31);
            $oneDay = new DateInterval('P1D');
            while ($date <= $end) {
                $day = DayContract::from($resolved->day($date), $resolved->provenance())->toArray();
                foreach ($day['celebration'] as $office) {
                    if ($office['kind'] === 'commemoration-only') {
                        $offenders[] = sprintf('%s: %s', $date->format('Y-m-d'), $office['names']['la']);
                    }
                }
                $date = $date->add($oneDay);
            }
        }

        self::assertSame([], $offenders, "A commemoration-only office was celebrated:\n  "
            . implode("\n  ", $offenders));
    }
}
