<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Precedence;

use DateInterval;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\Computus;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the Sacred Triduum precedence bug surfaced by the
 * validation harness (#45): a III-class saint could displace Holy Thursday.
 *
 * The apex "triduum" tier is awarded by {@see \Directorium\Core\Precedence\Rubrics1962Precedence}
 * to the day's own liturgical office — a feria of Holy Thursday, Good Friday, or
 * Holy Saturday. Before the fix the tier was awarded to *every* observance falling
 * on those dates, so a coincident saint (e.g. St John of Capistrano on 28 March,
 * Holy Thursday 2024) tied the feria at the apex and won the id-based tie-break,
 * suppressing the Triduum. Nothing outranks the Triduum (n. 91, line 2, below only
 * the supreme feasts), and it admits no commemoration (n. 23).
 */
final class TriduumPrecedenceTest extends TestCase
{
    public function testCoincidentSaintDoesNotDisplaceHolyThursday(): void
    {
        // 2024: Easter is 31 March, so Holy Thursday is 28 March — the feast of
        // St John of Capistrano (III class) in the 1962 calendar.
        $resolved = DayResolver::for1962()->resolveYear(2024);
        $day = DayContract::from(
            $resolved->day(TemporalCalendar::utcDate(2024, 3, 28)),
            $resolved->provenance()
        )->toArray();

        $celebration = $day['celebration'][0] ?? null;
        self::assertNotNull($celebration, 'Holy Thursday must have a celebration.');
        self::assertSame('feria', $celebration['kind'], 'Holy Thursday must be celebrated as its own feria.');
        self::assertSame(1, $celebration['rankOrdinal'], 'The Holy Thursday feria is first class.');
        self::assertStringContainsString(
            'Cena Domini',
            $celebration['names']['la'],
            'The celebration must be the Mass of the Lord’s Supper, not the coincident saint.'
        );

        // The saint yields entirely — displaced and omitted, never commemorated (n. 23).
        self::assertSame([], $day['commemoration'], 'The Triduum admits no commemoration.');
        $displacedFeast = array_values(array_filter(
            $day['displaced'],
            static fn (array $office): bool => $office['kind'] === 'feast'
        ));
        self::assertNotSame([], $displacedFeast, 'St John of Capistrano should be displaced.');
        self::assertSame('omit', $displacedFeast[0]['outcome'], 'The impeded saint is omitted, not transferred.');
    }

    /**
     * Across a wide span of years, no day of the Sacred Triduum is ever won by
     * anything but its own feria — the invariant the fix restores.
     */
    public function testNoTriduumDayIsEverWonByANonFeria(): void
    {
        $offenders = [];
        for ($year = 1970; $year <= 2040; $year++) {
            $easter = Computus::gregorianEaster($year);
            $resolved = DayResolver::for1962()->resolveYear($year);
            foreach ([3, 2, 1] as $daysBefore) {
                $date = $easter->sub(new DateInterval('P' . $daysBefore . 'D'));
                $day = DayContract::from($resolved->day($date), $resolved->provenance())->toArray();
                $celebration = $day['celebration'][0] ?? null;
                if ($celebration !== null && $celebration['kind'] !== 'feria') {
                    $offenders[] = sprintf('%s: %s', $date->format('Y-m-d'), $celebration['names']['la']);
                }
            }
        }

        self::assertSame([], $offenders, "A non-feria office won a day of the Sacred Triduum:\n  "
            . implode("\n  ", $offenders));
    }
}
