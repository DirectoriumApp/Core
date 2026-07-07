<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Full-year structural sweep for the newly-built editions (#453). Flipping 1954 and 1955 to
 * built exposes every day of every year to the public boundary; this proves the whole year
 * resolves cleanly under each edition — no latent crash on a day the targeted unit tests never
 * reach, exactly one principal office per day, and a contract that serialises and stamps the
 * right edition.
 *
 * This is a STRUCTURAL sweep, not an oracle sweep: it asserts the engine's own invariants across
 * the year, oracle-free. The day-by-day agreement with Divinum Officium is the pinned fixture in
 * {@see \Directorium\Core\Tests\Validation\HistoricalEditionOracleTest} and the maintainer's live
 * full-year run; those two are complementary — this catches crashes and shape violations, the
 * oracle catches wrong-but-well-formed answers.
 *
 * The years span the temporal engine's stress points regardless of edition window (the sweep is a
 * crash/shape test, so an edition resolved outside its historical years is intentional coverage,
 * not an accuracy claim): a very early Easter (1913 = 23 Mar) and the latest possible (1943 =
 * 25 Apr), two leap years (1960, 2000), and 1954 itself (the early-Septuagesima year whose
 * anticipated-Sunday residual is documented in KNOWN-LIMITATIONS).
 *
 * @group edition-1954
 * @group edition-1955
 */
final class PreConciliarYearSweepTest extends TestCase
{
    /** Easter extremes, leap years, and the documented 1954 edge — enough to exercise the skeleton. */
    private const YEARS = [1913, 1943, 1954, 1960, 2000];

    /**
     * @dataProvider editions
     */
    public function testEveryDayOfEveryYearResolvesToExactlyOnePrincipalOffice(
        string $editionUrn
    ): void {
        $resolver = DayResolver::forEdition(RubricSystem::fromString($editionUrn));
        $oneDay = new DateInterval('P1D');
        $daysChecked = 0;

        foreach (self::YEARS as $year) {
            $resolved = $resolver->resolveYear($year);
            $provenance = $resolved->provenance();
            $date = TemporalCalendar::utcDate($year, 1, 1);
            $end = TemporalCalendar::utcDate($year, 12, 31);

            while ($date <= $end) {
                $day = $resolved->day($date);
                $stamp = $date->format('Y-m-d');

                self::assertFalse($day->isEmpty(), "$editionUrn $stamp: a day resolved empty.");
                self::assertCount(
                    1,
                    $day->celebration(),
                    "$editionUrn $stamp: expected exactly one celebrated principal office."
                );

                // The celebrated office has a resolvable identity and colour (typed — access proves it).
                $celebration = $day->celebration()[0];
                self::assertNotSame('', $celebration->id()->toString(), "$editionUrn $stamp: blank id.");
                self::assertNotSame(
                    '',
                    $celebration->colour()->base()->value(),
                    "$editionUrn $stamp: blank colour."
                );

                // The contract serialises without throwing and stamps the resolving edition.
                $array = DayContract::from($day, $provenance)->toArray();
                self::assertSame($editionUrn, $array['edition'], "$editionUrn $stamp: wrong edition stamp.");
                self::assertSame('roman', $array['rite'], "$editionUrn $stamp: wrong rite.");

                $date = $date->add($oneDay);
                $daysChecked++;
            }
        }

        // A guard on the guard: prove the loop actually ran the whole span (5 years, two leap:
        // 1960 and 2000, so 365*3 + 366*2 = 1827 days).
        self::assertSame(1827, $daysChecked, 'the sweep must cover every day of every year');
    }

    /**
     * @return array<string, array{string}>
     */
    public function editions(): array
    {
        return [
            'Divino Afflatu (1954)' => [RubricSystem::DIVINO_AFFLATU],
            'Cum nostra (1955)' => [RubricSystem::RUBRICAE_1955],
        ];
    }
}
