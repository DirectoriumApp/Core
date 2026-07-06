<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Overlay;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Overlay\CalendarCatalog;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The calendar selector (#78): the catalog builds the resolver and the contract
 * descriptor for the universal 1962 calendar (null) or a particular calendar named by
 * slug or overlay URN, and rejects an unknown selector.
 */
final class CalendarCatalogTest extends TestCase
{
    public function testNullSelectorResolvesTheUniversalCalendar(): void
    {
        $day = (new CalendarCatalog())->resolver(null)->resolveDay(self::date('2026-09-03'));

        self::assertSame('roman:sanctorale:pius-x', $day->celebration()[0]->id()->toString());
        self::assertSame(3, $day->celebration()[0]->rank()->ordinal(), 'St Pius X is III class universally.');
    }

    public function testSspxSelectorResolvesUnderTheOverlay(): void
    {
        $day = (new CalendarCatalog())->resolver('sspx')->resolveDay(self::date('2026-09-03'));

        self::assertSame('roman:sanctorale:pius-x', $day->celebration()[0]->id()->toString());
        self::assertSame(1, $day->celebration()[0]->rank()->ordinal(), 'St Pius X is I class under SSPX.');
    }

    public function testNullSelectorHasNoDescriptor(): void
    {
        self::assertNull((new CalendarCatalog())->descriptor(null));
    }

    public function testSspxDescriptorNamesTheOverlay(): void
    {
        $descriptor = (new CalendarCatalog())->descriptor('sspx');

        self::assertNotNull($descriptor);
        self::assertSame('directorium:overlay:roman:sspx', $descriptor->id());
        self::assertSame('Society of Saint Pius X', $descriptor->name());
    }

    public function testAcceptsTheFullOverlayUrnAsASelector(): void
    {
        $bySlug = (new CalendarCatalog())->descriptor('sspx');
        $byUrn = (new CalendarCatalog())->descriptor('directorium:overlay:roman:sspx');

        self::assertNotNull($bySlug);
        self::assertNotNull($byUrn);
        self::assertSame($bySlug->toArray(), $byUrn->toArray());
    }

    public function testListsTheParticularCalendars(): void
    {
        self::assertContains('sspx', (new CalendarCatalog())->particularCalendars());
    }

    public function testUnknownCalendarSelectorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalendarCatalog())->resolver('no-such-calendar');
    }

    public function testUnknownDescriptorSelectorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CalendarCatalog())->descriptor('no-such-calendar');
    }

    private static function date(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd, new DateTimeZone('UTC'));
    }
}
