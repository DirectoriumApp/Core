<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Edition;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Precedence\ResolvedYear;
use PHPUnit\Framework\TestCase;

/**
 * The four 1954 (Divino Afflatu) privileged TEMPORAL octaves resolved end-to-end (#453):
 * Epiphany and Corpus Christi (2nd order), the Ascension and the Sacred Heart (3rd order).
 * The season-fillers mint them, gated on the edition ({@see \Directorium\Core\Temporal\TemporalAttributes::has}),
 * and {@see \Directorium\Core\Precedence\Rubrics1954Precedence} places them by tier. The base
 * (1962) edition declares none of the archetypes, so it mints no octave — proven here and by
 * the byte-identical golden fixture.
 *
 * The load-bearing correctness case is the transfer that must SKIP the Corpus Christi octave: a
 * Double II class feast impeded into the octave floats past its 2nd-order days (which yield only
 * to a Double of the I class, so are never a free landing) and lands only on the following
 * Sacred Heart 3rd-order day-within (which yields to any feast above a simple). Verified for the
 * Queenship of the BVM in 1956 (Maria Regina, 31 May -> 9 June). Dates cross-checked by computus;
 * the octave classes are grounded in docs/design/rubric-system-model.md (octave inventory).
 */
final class TemporalOctaveResolutionTest extends TestCase
{
    private static function daYear(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::divinoAfflatu())->resolveYear($year);
    }

    private static function on(ResolvedYear $year, string $date): LiturgicalDay
    {
        return $year->day(new DateTimeImmutable($date, new DateTimeZone('UTC')));
    }

    private static function celebrationId(LiturgicalDay $day): ?string
    {
        $celebration = $day->celebration();

        return $celebration === [] ? null : $celebration[0]->id()->toString();
    }

    /** The celebration role of an office on the day (celebration / commemoration / displaced), or null. */
    private static function roleOf(LiturgicalDay $day, string $id): ?string
    {
        foreach ($day->offices() as $office) {
            if ($office->observance()->id()->toString() === $id) {
                return $office->role()->value();
            }
        }

        return null;
    }

    // --- 2nd order: the Epiphany octave (Jan 6 -> octave day Jan 13) ----------------------

    public function testTheEpiphanyOctaveDayAndADayWithinAreCelebratedOnAFreeDay(): void
    {
        // 1954: Epiphany falls on Wed 6 Jan, so 7 Jan (day within) and 13 Jan (octave day) carry
        // no competing feast and the temporal octave office is the day's celebration.
        $year = self::daYear(1954);

        self::assertSame(
            'roman:temporale:epiphany:within-octave:day-2',
            self::celebrationId(self::on($year, '1954-01-07'))
        );
        self::assertSame(
            'roman:temporale:epiphany:octave-day',
            self::celebrationId(self::on($year, '1954-01-13'))
        );
    }

    public function testTheSundayWithinTheEpiphanyOctaveKeepsItsOfficeAndIsNotOverwritten(): void
    {
        // The Sunday within the octave (10 Jan 1954) is the Holy Family Sunday. The octave does
        // not overwrite it with a numbered day-within: the Holy Family is celebrated over the
        // first Sunday after the Epiphany and no epiphany:within-octave office is placed there.
        $day = self::on(self::daYear(1954), '1954-01-10');

        self::assertSame('roman:temporale:epiphany:holy-family', self::celebrationId($day));
        self::assertNull(self::roleOf($day, 'roman:temporale:epiphany:within-octave:day-5'));
    }

    // --- 2nd order: the Corpus Christi octave --------------------------------------------

    public function testTheCorpusChristiDayWithinOutranksALesserSanctoralFeast(): void
    {
        // 18 Jun 1954, a day within the Corpus Christi octave. A 2nd-order day-within yields only
        // to a Double of the I class, so it is celebrated over St Ephraem (a lesser feast), which
        // is commemorated — the privilege that keeps a transferred Double II from landing here.
        $day = self::on(self::daYear(1954), '1954-06-18');

        self::assertSame('roman:temporale:paschal:corpus-christi-octave:day-2', self::celebrationId($day));
        self::assertSame('commemoration', self::roleOf($day, 'roman:sanctorale:ephraem-syrus'));
    }

    public function testTheCorpusChristiOctaveDayIsCommemoratedUnderADoubleFirstClass(): void
    {
        // 24 Jun 1954 is the Octave Day of Corpus Christi AND the Nativity of St John the Baptist
        // (a Double of the I class). The Double I wins; the octave day is always commemorated.
        $day = self::on(self::daYear(1954), '1954-06-24');

        self::assertSame('roman:sanctorale:nativitas-ioannis-baptistae', self::celebrationId($day));
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:temporale:paschal:corpus-christi-octave:octave-day')
        );
    }

    // --- 3rd order: the Ascension and the Sacred Heart -----------------------------------

    public function testTheAscensionOctaveDayIsCelebratedAndADayWithinYieldsToAFeast(): void
    {
        // 1954: Ascension Thu 27 May; the octave day (3 Jun) is free and celebrated. A day within
        // (28 May) yields to St Augustine of Canterbury (a feast above a simple) and the octave
        // is commemorated — the 3rd-order behaviour, identical to the Christmas octave within.
        $year = self::daYear(1954);

        self::assertSame(
            'roman:temporale:paschal:ascension-octave:octave-day',
            self::celebrationId(self::on($year, '1954-06-03'))
        );
        self::assertSame(
            'commemoration',
            self::roleOf(self::on($year, '1954-05-28'), 'roman:temporale:paschal:ascension-octave:day-2')
        );
    }

    public function testTheSundayAfterTheAscensionKeepsItsOfficeWithinTheOctave(): void
    {
        // 30 May 1954 is the Sunday after the Ascension, inside the octave; it keeps its own
        // office and is not overwritten by a numbered day-within.
        self::assertSame(
            'roman:temporale:paschal:sunday-after-ascension',
            self::celebrationId(self::on(self::daYear(1954), '1954-05-30'))
        );
    }

    public function testTheSacredHeartOctaveDayIsCommemoratedUnderAHigherFeast(): void
    {
        // 2 Jul 1954 is the Octave Day of the Sacred Heart AND the Visitation of the BVM (a
        // Double II class). The Visitation wins; the Sacred Heart octave day is commemorated.
        $day = self::on(self::daYear(1954), '1954-07-02');

        self::assertSame('roman:sanctorale:visitatio', self::celebrationId($day));
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:temporale:paschal:sacred-heart-octave:octave-day')
        );
    }

    // --- The load-bearing #453 transfer-skip case ----------------------------------------

    public function testATransferredFeastSkipsTheCorpusOctaveAndLandsOnTheSacredHeartWithin(): void
    {
        // 1956: the Queenship of the BVM (Maria Regina, a Double II class, 31 May) is impeded by
        // Corpus Christi (also 31 May). It cannot land on any day within the Corpus octave (2nd
        // order — yields only to a Double I), so it floats past 1-7 June and the Sacred Heart
        // feast (8 June), landing on 9 June, the first Sacred Heart day-within (3rd order, which
        // yields to it). "9 June, not 1 June" (rubric-system-model.md; #453).
        $year = self::daYear(1956);
        $maria = 'roman:sanctorale:maria-regina';

        // Impeded on its own date, and NOT celebrated on the first Corpus octave day (1 June).
        self::assertSame('roman:temporale:paschal:corpus-christi', self::celebrationId(self::on($year, '1956-05-31')));
        self::assertSame('displaced', self::roleOf(self::on($year, '1956-06-01'), $maria));
        self::assertNotSame($maria, self::celebrationId(self::on($year, '1956-06-01')));

        // Lands on 9 June — the first day within the Sacred Heart octave.
        self::assertSame($maria, self::celebrationId(self::on($year, '1956-06-09')));
    }

    // --- The transferred-anchor case: a feast of the Lord keeps its day + octave -----------

    public function testAFeastOfTheLordKeepsItsDayAndOctaveWhenACoincidentSaintTransfers(): void
    {
        // 2038: Easter is 25 April (the latest possible), so Corpus Christi (Easter+60) falls on
        // 24 June — the Nativity of St John the Baptist, a Double I saint. The feast of the Lord
        // wins the I-class line; St John is transferred to 25 June. Corpus Christi keeps 24 June,
        // so its octave stays attached: 25 June is day-2 within the octave (not a second Corpus
        // Christi). Guards the root-cause of the transferred-anchor octave-detachment: were the
        // feast of the Lord itself transferred off 24 June, its own octave day-2 would collide
        // with it on the same day.
        $year = self::daYear(2038);

        self::assertSame(
            'roman:temporale:paschal:corpus-christi',
            self::celebrationId(self::on($year, '2038-06-24'))
        );
        self::assertSame(
            'displaced',
            self::roleOf(self::on($year, '2038-06-24'), 'roman:sanctorale:nativitas-ioannis-baptistae')
        );
        // 25 June: the transferred St John is celebrated with the Corpus octave day-2 commemorated
        // — the octave correctly begins the day AFTER the feast, never on the feast's own day.
        self::assertSame(
            'commemoration',
            self::roleOf(self::on($year, '2038-06-25'), 'roman:temporale:paschal:corpus-christi-octave:day-2')
        );
    }

    // --- The has() gate: the base (1962) edition mints no temporal octave ----------------

    public function testTheNineteenSixtyEditionPlacesNoPrivilegedTemporalOctave(): void
    {
        // The base edition declares none of the four octave archetypes, so has() is false and the
        // season-fillers mint no octave — the reason the 1962 golden fixture is byte-identical.
        // Resolve 1954 under the 1962 engine and assert none of the octave slugs is placed on the
        // dates that carry one under Divino Afflatu.
        $year = DayResolver::for1962()->resolveYear(1954);
        $stems = [
            'epiphany:within-octave',
            'epiphany:octave-day',
            'ascension-octave',
            'corpus-christi-octave',
            'sacred-heart-octave',
        ];
        foreach (['1954-01-07', '1954-01-13', '1954-06-03', '1954-06-18', '1954-07-02'] as $date) {
            foreach (self::on($year, $date)->offices() as $office) {
                $id = $office->observance()->id()->toString();
                foreach ($stems as $stem) {
                    self::assertStringNotContainsString(
                        $stem,
                        $id,
                        sprintf('1962 minted a privileged temporal octave on %s: %s', $date, $id)
                    );
                }
            }
        }
    }
}
