<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\Computus;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45), oracle #1 — Easter vs PHP's {@see easter_days()} (#46).
 *
 * The entire temporal cycle hangs off Easter, so the cheapest, widest "re-prove
 * 100%" is to check the clean-room {@see Computus} against a completely
 * independent implementation. PHP's `calendar` extension ships one: `easter_days()`
 * returns the number of days after 21 March on which Easter falls. Two unrelated
 * algorithms agreeing on every year is strong evidence neither has a bug.
 *
 * The oracle is queried in {@see CAL_EASTER_ALWAYS_GREGORIAN} mode: our computus
 * is the Gregorian one, valid from the 1583 reform onward, whereas
 * `easter_days()`'s default mode switches to the Julian reckoning before 1753 and
 * would (correctly, for its own contract) disagree. Pinning the mode compares
 * like with like across the whole Gregorian range.
 *
 * Range: {@see Computus::GREGORIAN_REFORM_YEAR} (1583, the first Gregorian Easter
 * and the computus's own floor — one year below the whole-year resolver floor,
 * because Easter itself is well-defined there) through {@see LAST_YEAR} (2200, the
 * same horizon the golden fixture freezes). That is 618 consecutive years proven
 * on every run.
 */
final class EasterOracleTest extends TestCase
{
    /** The upper horizon, matching the golden fixture's {@see \Directorium\Core\Tests\Golden\GoldenYear::LAST_YEAR}. */
    private const LAST_YEAR = 2200;

    /**
     * Well-known Gregorian Easter dates — the two calendrical extremes, the
     * editio-typica year, and two contemporary anchors — verified against the
     * engine directly so the cross-check has a human-readable floor of trust that
     * does not itself depend on `easter_days()`.
     *
     * @return array<string, array{int, string}>
     */
    public function knownEasterDates(): array
    {
        return [
            'earliest possible (22 Mar) 1818' => [1818, '1818-03-22'],
            'editio typica year 1962'         => [1962, '1962-04-22'],
            'millennium 2000'                 => [2000, '2000-04-23'],
            'contemporary 2024'               => [2024, '2024-03-31'],
            'latest possible (25 Apr) 2038'   => [2038, '2038-04-25'],
        ];
    }

    /**
     * @dataProvider knownEasterDates
     */
    public function testEasterMatchesKnownDates(int $year, string $expected): void
    {
        self::assertSame(
            $expected,
            Computus::gregorianEaster($year)->format('Y-m-d'),
            sprintf('Computus put Easter %d on the wrong date.', $year)
        );
    }

    /**
     * The wide-range proof: for every Gregorian year in range, the engine's Easter
     * must equal the `easter_days()` oracle. Divergences are collected so a failure
     * names every offending year, not just the first.
     */
    public function testEasterMatchesPhpEasterDaysForEveryGregorianYear(): void
    {
        if (!extension_loaded('calendar')) {
            self::markTestSkipped('The `calendar` extension (easter_days) is not loaded; oracle unavailable.');
        }

        $mismatches = [];
        for ($year = Computus::GREGORIAN_REFORM_YEAR; $year <= self::LAST_YEAR; $year++) {
            $engine = Computus::gregorianEaster($year)->format('Y-m-d');
            $oracle = self::easterFromOracle($year)->format('Y-m-d');
            if ($engine !== $oracle) {
                $mismatches[] = sprintf('%d: engine %s vs easter_days() %s', $year, $engine, $oracle);
            }
        }

        self::assertSame([], $mismatches, sprintf(
            "Computus disagreed with easter_days() on %d year(s):\n  %s",
            count($mismatches),
            implode("\n  ", array_slice($mismatches, 0, 10))
        ));
    }

    /**
     * Easter for a year as the `easter_days()` oracle reckons it: 21 March plus the
     * returned day-count, in the same UTC-midnight frame the engine uses.
     */
    private static function easterFromOracle(int $year): DateTimeImmutable
    {
        $days = easter_days($year, CAL_EASTER_ALWAYS_GREGORIAN);

        return (new DateTimeImmutable(sprintf('%04d-03-21 00:00:00', $year), new DateTimeZone('UTC')))
            ->add(new DateInterval('P' . $days . 'D'));
    }
}
