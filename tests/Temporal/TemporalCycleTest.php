<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Temporal;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Temporal\NovusOrdoTemporalCycle;
use Directorium\Core\Temporal\TraditionalTemporalCycle;
use PHPUnit\Framework\TestCase;

/**
 * The edition-selected temporal-cycle seam (#258): the traditional editions compose
 * their Proper of Time from the historic fillers (with a Triduum window from Holy
 * Week), the Novus Ordo from its own set. The resolver reads a year through
 * {@see \Directorium\Core\Temporal\YearTemporalCycle} rather than a hard-coded filler
 * list; the 1962 golden fixture proves the traditional path is byte-identical, and
 * this pins the seam directly.
 */
final class TemporalCycleTest extends TestCase
{
    private static function utc(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC'));
    }

    public function testTraditionalCycleMintsTheHistoricOffices(): void
    {
        $cycle = (new TraditionalTemporalCycle())
            ->forYear(2024, Corpus::default(), 'roman-rubricae-1960');

        self::assertSame(
            'roman:temporale:christmas:nativity',
            $cycle->office(self::utc('2024-12-25'))->id()->toString()
        );
    }

    public function testTraditionalCycleReportsTheTriduum(): void
    {
        $cycle = (new TraditionalTemporalCycle())
            ->forYear(2024, Corpus::default(), 'roman-rubricae-1960');

        self::assertTrue($cycle->isTriduum(self::utc('2024-03-28')), 'Maundy Thursday 2024 is in the Triduum.');
        self::assertTrue($cycle->isTriduum(self::utc('2024-03-29')), 'Good Friday 2024 is in the Triduum.');
        self::assertTrue($cycle->isTriduum(self::utc('2024-03-30')), 'Holy Saturday 2024 is in the Triduum.');
        self::assertFalse($cycle->isTriduum(self::utc('2024-03-27')), 'Wednesday of Holy Week is not the Triduum.');
        self::assertFalse($cycle->isTriduum(self::utc('2024-07-01')), 'An ordinary July day is not.');
    }

    public function testNovusOrdoCycleMintsOrdinaryTimeAndChristmas(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())
            ->forYear(2024, Corpus::default(), 'roman-novus-ordo-2002');

        // Ordinary Time in the green blocks …
        self::assertSame(
            'roman:temporale:ordinary-time:sunday-2',
            $cycle->office(self::utc('2024-01-14'))->id()->toString()
        );
        // … and the Advent → Christmas block on either side (the Nativity in December).
        self::assertSame(
            'roman:temporale:christmas:nativity',
            $cycle->office(self::utc('2024-12-25'))->id()->toString()
        );
    }

    /**
     * The Novus-Ordo cycle now carries a Triduum source (its {@see \Directorium\Core\Temporal\NovusOrdo\PaschalCycle}
     * paschal filler), so it reports the three days of the Sacred Triduum. Easter 2024 is 31
     * March, so the Triduum is 28–30 March.
     */
    public function testNovusOrdoCycleReportsTheTriduum(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())
            ->forYear(2024, Corpus::default(), 'roman-novus-ordo-2002');

        self::assertTrue($cycle->isTriduum(self::utc('2024-03-28')), 'Maundy Thursday 2024 is in the Triduum.');
        self::assertTrue($cycle->isTriduum(self::utc('2024-03-29')), 'Good Friday 2024 is in the Triduum.');
        self::assertTrue($cycle->isTriduum(self::utc('2024-03-30')), 'Holy Saturday 2024 is in the Triduum.');
        self::assertFalse($cycle->isTriduum(self::utc('2024-03-27')), 'Wednesday of Holy Week is not the Triduum.');
        self::assertFalse($cycle->isTriduum(self::utc('2024-07-01')), 'An ordinary July day is not.');
    }

    /**
     * The paschal half now fills the window Ordinary Time leaves between Ash Wednesday and
     * Pentecost: the Sunday of the Passion (Palm Sunday), the Triduum, Easter, and the fifty
     * days to Pentecost all resolve for the reformed edition.
     */
    public function testNovusOrdoCycleMintsThePaschalHalf(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())
            ->forYear(2024, Corpus::default(), 'roman-novus-ordo-2002');

        self::assertSame(
            'roman:temporale:paschal:easter',
            $cycle->office(self::utc('2024-03-31'))->id()->toString()
        );
        self::assertSame(
            'roman:temporale:paschal:pentecost',
            $cycle->office(self::utc('2024-05-19'))->id()->toString()
        );
    }
}
