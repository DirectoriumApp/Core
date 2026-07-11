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
    private const NO_FIXTURE = __DIR__ . '/../fixtures/novus-ordo-temporal';

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

    public function testNovusOrdoCycleMintsOrdinaryTime(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())
            ->forYear(2024, Corpus::at(self::NO_FIXTURE), 'roman-novus-ordo-2002');

        self::assertSame(
            'roman:temporale:ordinary-time:sunday-2',
            $cycle->office(self::utc('2024-01-14'))->id()->toString()
        );
    }

    /**
     * The Novus-Ordo cycle carries no Triduum source yet (its Holy Week filler arrives
     * with the general-calendar corpus, #108), so it reports false — and it is not yet
     * reached through the resolver, which refuses the unbuilt edition at the boundary.
     */
    public function testNovusOrdoCycleHasNoTriduumYet(): void
    {
        $cycle = (new NovusOrdoTemporalCycle())
            ->forYear(2024, Corpus::at(self::NO_FIXTURE), 'roman-novus-ordo-2002');

        self::assertFalse($cycle->isTriduum(self::utc('2024-03-29')));
    }
}
