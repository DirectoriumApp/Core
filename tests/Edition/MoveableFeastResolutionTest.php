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
 * The two moveable feasts the pre-1955 rite keeps that the 1962 rubrics dropped (#453), resolved
 * end-to-end: the Passiontide **Seven Sorrows of the BVM** (the Friday in Passion Week, Easter-9,
 * a Duplex maius) and the moveable **Solemnity of St Joseph** (the Wednesday before the 3rd Sunday
 * after Easter, Easter+17, a Duplex I classis of a saint) with its 1954-only **common octave**
 * (days within Easter+18..+23 semidouble; octave day Easter+24 greater double). {@see MovableFeasts}
 * places the two feasts and {@see Eastertide} mints the octave, both gated on the edition
 * ({@see \Directorium\Core\Temporal\TemporalAttributes::has}); {@see Rubrics1954Precedence} routes them.
 *
 * The load-bearing case is the COMMON octave's day-within OMITTED under a Double of the I/II class
 * (unlike the always-commemorated privileged octaves): verified for Sts Philip & James (1 May 2020,
 * a Duplex II classis). Its octave DAY, a full greater double, is commemorated not omitted (1 May
 * 1996). Facts grounded in docs/design/rubric-system-model.md (Seam 8); dates by computus.
 */
final class MoveableFeastResolutionTest extends TestCase
{
    private const SEVEN_SORROWS = 'roman:temporale:paschal:passion-week:septem-dolorum';
    private const SOLEMNITY = 'roman:temporale:paschal:solemnitas-ioseph';
    private const PASSION_FERIA = 'roman:temporale:paschal:passion-week:feria-6';

    private static function daYear(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::divinoAfflatu())->resolveYear($year);
    }

    private static function cnYear(int $year): ResolvedYear
    {
        return DayResolver::forEdition(RubricSystem::rubricae1955())->resolveYear($year);
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

    // --- The Passiontide Seven Sorrows (Easter-9) ----------------------------------------

    public function testSevenSorrowsIsCelebratedWithThePassionWeekFeriaCommemorated(): void
    {
        // 1954: Easter 18 Apr, so the Friday in Passion Week is 9 Apr. The greater double is
        // celebrated (white), the greater feria of Passion Week commemorated (violet) — a feast
        // displaces but never omits a greater feria.
        $day = self::on(self::daYear(1954), '1954-04-09');

        self::assertSame(self::SEVEN_SORROWS, self::celebrationId($day));
        self::assertSame('commemoration', self::roleOf($day, self::PASSION_FERIA));
    }

    public function testSevenSorrowsIsKeptInBothTheDivinoAfflatuAndCumNostraEditions(): void
    {
        // A Duplex maius is a double, untouched by the Cum nostra grade-transform, so the feast
        // carries into 1955 unchanged (it is the 1960 rubrics, not 1955, that dropped it).
        self::assertSame(self::SEVEN_SORROWS, self::celebrationId(self::on(self::daYear(1954), '1954-04-09')));
        self::assertSame(self::SEVEN_SORROWS, self::celebrationId(self::on(self::cnYear(1954), '1954-04-09')));
    }

    // --- The Solemnity of St Joseph (Easter+17) and its common octave (1954) --------------

    public function testTheSolemnityOfStJosephIsCelebratedOnTheWednesdayAfterEaster(): void
    {
        // 1954: Easter 18 Apr, so the Solemnity (Easter+17) is Wed 5 May. A Duplex I classis of a
        // saint (not the Lord), it is celebrated over the paschaltide feria and commemorates the
        // coincident St Pius V (5 May).
        $day = self::on(self::daYear(1954), '1954-05-05');

        self::assertSame(self::SOLEMNITY, self::celebrationId($day));
        self::assertSame('commemoration', self::roleOf($day, 'roman:sanctorale:pius-v'));
    }

    public function testASolemnityOctaveDayWithinIsCommemoratedUnderAHigherFeast(): void
    {
        // 6 May 1954 is the 2nd day within the octave; St John before the Latin Gate (a greater
        // double) is celebrated and the octave commemorated — a semidouble day-within yields to any
        // feast above a simple, the common octave commemorated.
        $day = self::on(self::daYear(1954), '1954-05-06');

        self::assertSame('roman:sanctorale:ioannes-ante-portam-latinam', self::celebrationId($day));
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:temporale:paschal:solemnitas-ioseph-octave:day-2')
        );
    }

    public function testTheSolemnityOctaveDayIsCelebratedOnAFreeDay(): void
    {
        // 12 May 1954 is the octave day (Easter+24), a greater double: it outranks the coincident
        // semidouble Ss Nereus, Achilleus, Domitilla & Pancratius, which it commemorates.
        $day = self::on(self::daYear(1954), '1954-05-12');

        self::assertSame(
            'roman:temporale:paschal:solemnitas-ioseph-octave:octave-day',
            self::celebrationId($day)
        );
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:sanctorale:nereus-achilleus-domitilla-pancratius')
        );
    }

    public function testTheSundayWithinTheSolemnityOctaveKeepsItsOffice(): void
    {
        // 9 May 1954 is the 3rd Sunday after Easter (Easter+21), the Sunday within the octave. It
        // keeps its own office and is not overwritten by a numbered day-within (the octave's
        // commemoration on the Sunday is the deferred Dominica-infra-Octavam layer, as for the
        // privileged octaves).
        $day = self::on(self::daYear(1954), '1954-05-09');

        self::assertSame('roman:temporale:paschal:paschaltide:sunday-3', self::celebrationId($day));
        self::assertNull(self::roleOf($day, 'roman:temporale:paschal:solemnitas-ioseph-octave:day-5'));
    }

    // --- The load-bearing common-octave omit rule ----------------------------------------

    public function testACommonOctaveDayWithinIsOmittedUnderADoubleOfTheSecondClass(): void
    {
        // 2020: Easter 12 Apr, so the Solemnity is 29 Apr and 1 May (Easter+19) is the 3rd day
        // within its octave AND Sts Philip & James (a Duplex II classis). Unlike the privileged
        // octaves, a COMMON octave's day-within is OMITTED — not merely commemorated — under a
        // Double of the I or II class: the octave office is displaced, absent from the day.
        $day = self::on(self::daYear(2020), '2020-05-01');

        self::assertSame('roman:sanctorale:philippus-iacobus', self::celebrationId($day));
        self::assertSame(
            'displaced',
            self::roleOf($day, 'roman:temporale:paschal:solemnitas-ioseph-octave:day-3')
        );
    }

    public function testACommonOctaveDayWithinIsCommemoratedUnderAPlainDouble(): void
    {
        // 30 Apr 2020 is the 2nd day within the octave and St Catherine of Siena (a plain Double,
        // NOT a Double of the I/II class). The octave is only OMITTED under a Double I/II; under an
        // ordinary double it is celebrated-elsewhere and the octave is commemorated. This is the
        // exact boundary of the omit rule.
        $day = self::on(self::daYear(2020), '2020-04-30');

        self::assertSame('roman:sanctorale:catharina-senensis', self::celebrationId($day));
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:temporale:paschal:solemnitas-ioseph-octave:day-2')
        );
    }

    public function testTheCommonOctaveDayIsCommemoratedNotOmittedUnderADoubleOfTheSecondClass(): void
    {
        // 1996: Easter 7 Apr, so the Solemnity is 24 Apr and 1 May (Easter+24) is the octave DAY
        // AND Sts Philip & James (Duplex II classis). The omit rule applies to the semidouble
        // days-within; the octave day is a full greater-double office and is COMMEMORATED, not
        // omitted, when it yields to the Double II (rubric-system-model.md, Seam 8, deferred item).
        $day = self::on(self::daYear(1996), '1996-05-01');

        self::assertSame('roman:sanctorale:philippus-iacobus', self::celebrationId($day));
        self::assertSame(
            'commemoration',
            self::roleOf($day, 'roman:temporale:paschal:solemnitas-ioseph-octave:octave-day')
        );
    }

    // --- 1955 (Cum nostra): feast kept, octave suppressed --------------------------------

    public function testTheSolemnityFeastIsKeptButItsOctaveIsSuppressedInTheCumNostraEdition(): void
    {
        // Cum nostra (1955) kept the feast (a double, unchanged) but abolished every octave except
        // Christmas/Easter/Pentecost. So under the 1955 edition the Solemnity is still celebrated on
        // 5 May 1954, but no octave office is placed on the days that carry one under Divino Afflatu.
        $year = self::cnYear(1954);
        $within = 'roman:temporale:paschal:solemnitas-ioseph-octave:day-2';
        $octaveDay = 'roman:temporale:paschal:solemnitas-ioseph-octave:octave-day';

        self::assertSame(self::SOLEMNITY, self::celebrationId(self::on($year, '1954-05-05')));
        self::assertNull(self::roleOf(self::on($year, '1954-05-06'), $within));
        self::assertNull(self::roleOf(self::on($year, '1954-05-12'), $octaveDay));
    }

    // --- The has() gate: the base (1962) edition mints neither feast ----------------------

    public function testTheNineteenSixtyEditionMintsNoMoveableFeastOfThisSlice(): void
    {
        // The base edition declares none of the four archetypes (the two feasts + the two octave
        // rows), so has() is false and neither MovableFeasts nor Eastertide mints them — the reason
        // the 1962 golden fixture is byte-identical. Sweep April–May 1954 under the 1962 engine.
        $year = DayResolver::for1962()->resolveYear(1954);
        $date = new DateTimeImmutable('1954-03-01', new DateTimeZone('UTC'));
        $end = new DateTimeImmutable('1954-05-31', new DateTimeZone('UTC'));
        $step = new \DateInterval('P1D');

        while ($date <= $end) {
            foreach ($year->day($date)->offices() as $office) {
                $id = $office->observance()->id()->toString();
                self::assertStringNotContainsString('septem-dolorum', $id, 'no Seven Sorrows in 1962');
                self::assertStringNotContainsString('solemnitas-ioseph', $id, 'no Solemnity of St Joseph in 1962');
            }
            $date = $date->add($step);
        }
    }
}
