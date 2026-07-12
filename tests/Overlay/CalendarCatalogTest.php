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

    public function testResolvesTheHistoricalEditionsUnderTheUniversalCalendar(): void
    {
        // The two selectors are orthogonal: with no particular calendar (null), naming the 1954 or
        // 1955 rubric system resolves that edition's universal calendar. Both are built since #453,
        // so the catalog no longer refuses them. The Assumption (15 Aug, first class everywhere) is
        // the stable probe.
        $catalog = new CalendarCatalog();

        foreach (['1954', '1955'] as $edition) {
            $day = $catalog->resolver(null, $edition)->resolveDay(self::date('1958-08-15'));
            self::assertSame(
                'roman:sanctorale:assumptio',
                $day->celebration()[0]->id()->toString(),
                "the $edition edition resolves the Assumption on 15 Aug"
            );
        }
    }

    public function testResolvesTheNovusOrdoUnderTheUniversalCalendar(): void
    {
        // The Novus Ordo 2002 is built (#256/#260), so the catalog resolves it at the public
        // boundary. The Assumption (15 Aug, a solemnity in the reformed calendar too) is the
        // stable probe; an Ordinary-Time feria additionally surfaces its electable optional
        // memorials, which the traditional editions never carry.
        $catalog = new CalendarCatalog();

        $assumption = $catalog->resolver(null, 'novus-ordo')->resolveDay(self::date('2025-08-15'));
        self::assertSame('roman:sanctorale:assumptio', $assumption->celebration()[0]->id()->toString());

        $feria = $catalog->resolver(null, 'roman:novus-ordo-2002')->resolveDay(self::date('2025-01-20'));
        self::assertNotSame([], $feria->optionalMemorials(), 'a NO feria offers its optional memorials');
    }

    public function testLayersTheSspxOverlayOverAHistoricalEdition(): void
    {
        // The orthogonal combination: a particular calendar layered over a non-1962 edition. St
        // Pius X (3 Sep) resolves under the SSPX overlay on the 1954 base without error — proving
        // $calendar and $rubricSystem compose, not just $calendar over the 1962 default.
        $day = (new CalendarCatalog())->resolver('sspx', '1954')->resolveDay(self::date('1954-09-03'));

        self::assertSame('roman:sanctorale:pius-x', $day->celebration()[0]->id()->toString());
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
