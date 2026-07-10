<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use DateInterval;
use Directorium\Core\Contract\DayContract;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Overlay\CorpusOverlayData;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45) — the Generic 1962 baseline overlay conformance gate (#83 / #87).
 *
 * "Generic 1962" is the IDENTITY overlay: a named, selectable particular calendar that
 * carries no operations, so the calendar it produces must be the universal 1962 calendar,
 * unaltered. This is a stronger guarantee than a pinned per-year fixture (which could drift
 * from the base golden and silently disagree): it is proved *structurally* — the engine
 * under the `generic-1962` overlay is resolved against the engine under the universal (null)
 * calendar, day for day, and every resolved office must be byte-identical. The only two
 * fields that may differ are the ones that exist precisely to name the selected calendar:
 * the `calendar` descriptor block and the `+overlayId` suffix on `corpusVersion`. Both are
 * asserted positively, so the overlay is proved to change the *label* and nothing else.
 *
 * A non-empty Generic overlay, a resolver that reads the wrong data, or any regression that
 * makes an overlay perturb resolution would fail here, naming the day.
 *
 * @group overlays
 */
final class GenericBaselineTest extends TestCase
{
    private const CALENDAR = 'generic-1962';
    private const URN = 'directorium:overlay:roman:generic-1962';
    private const NAME = 'Generic 1962';

    /** A representative sweep: a leap year and two common years (2024 leap + 2025 + 2026 = 1096 days). */
    private const YEARS = [2024, 2025, 2026];

    /** The overlay is a first-class, selectable calendar the catalogue advertises and describes. */
    public function testTheGenericOverlayIsAdvertisedAndDescribed(): void
    {
        $catalog = new CalendarCatalog();

        self::assertContains(
            self::CALENDAR,
            $catalog->particularCalendars(),
            'The Generic 1962 overlay must be advertised as a selectable calendar.'
        );

        $descriptor = $catalog->descriptor(self::CALENDAR);
        self::assertNotNull($descriptor, 'The Generic 1962 overlay must carry a contract descriptor.');
        self::assertSame(self::URN, $descriptor->id());
        self::assertSame(self::NAME, $descriptor->name());

        // The identity overlay carries no operations — that is what makes it the baseline.
        $overlay = (new CorpusOverlayData())->overlay(self::CALENDAR);
        self::assertSame(self::URN, $overlay->id());
        self::assertSame([], $overlay->operations(), 'The Generic 1962 overlay must carry zero operations.');
    }

    /**
     * The identity proof: every day of the sweep resolves identically under the Generic
     * overlay and under the universal calendar, except for the two calendar-selection stamps.
     */
    public function testGenericResolvesIdenticallyToTheUniversalCalendar(): void
    {
        $catalog = new CalendarCatalog();
        $oneDay = new DateInterval('P1D');
        $daysChecked = 0;

        foreach (self::YEARS as $year) {
            $base = $catalog->resolver(null)->resolveYear($year);
            $generic = $catalog->resolver(self::CALENDAR)->resolveYear($year);
            $baseProvenance = $base->provenance();
            $genericProvenance = $generic->provenance();
            $genericDescriptor = $catalog->descriptor(self::CALENDAR);

            $date = TemporalCalendar::utcDate($year, 1, 1);
            $end = TemporalCalendar::utcDate($year, 12, 31);

            while ($date <= $end) {
                $stamp = $date->format('Y-m-d');

                $baseArray = DayContract::from($base->day($date), $baseProvenance)->toArray();
                $genericArray = DayContract::from(
                    $generic->day($date),
                    $genericProvenance,
                    $genericDescriptor
                )->toArray();

                self::assertSame(
                    $this->liturgicalContent($baseArray),
                    $this->liturgicalContent($genericArray),
                    "The Generic overlay diverges from the universal calendar on $stamp — an identity "
                    . 'overlay must not change the resolved office.'
                );

                $date = $date->add($oneDay);
                $daysChecked++;
            }
        }

        // A guard on the guard: 2024 (leap) + 2025 + 2026 = 1096 days actually compared.
        self::assertSame(1096, $daysChecked, 'the sweep must cover every day of every year');
    }

    /** The two intended stamps — and only those — carry the calendar selection. */
    public function testOnlyTheCalendarStampsDifferFromTheUniversalContract(): void
    {
        $catalog = new CalendarCatalog();
        $base = $catalog->resolver(null)->resolveYear(2026);
        $generic = $catalog->resolver(self::CALENDAR)->resolveYear(2026);

        $date = TemporalCalendar::utcDate(2026, 9, 3); // St Pius X — a day with real content
        $baseArray = DayContract::from($base->day($date), $base->provenance())->toArray();
        $genericArray = DayContract::from(
            $generic->day($date),
            $generic->provenance(),
            $catalog->descriptor(self::CALENDAR)
        )->toArray();

        // The universal contract names no particular calendar; the Generic one names the overlay
        // under `calendar.particular`. The astronomical sub-block (date-only) is unchanged.
        self::assertArrayNotHasKey('particular', $baseArray['calendar']);
        self::assertSame(
            ['id' => self::URN, 'name' => self::NAME],
            $genericArray['calendar']['particular']
        );
        self::assertSame(
            $baseArray['calendar']['astronomical'],
            $genericArray['calendar']['astronomical'],
            'An identity overlay must not touch the astronomical block.'
        );

        // corpusVersion carries the overlay id as a `base+overlayId` suffix, and only that.
        self::assertSame(
            $baseArray['corpusVersion'] . '+' . self::URN,
            $genericArray['corpusVersion'],
            'The Generic overlay must stamp corpusVersion with exactly its overlay id.'
        );
    }

    /**
     * The contract with the two calendar-selection stamps removed — the liturgical content
     * that an identity overlay must leave untouched. Only the `corpusVersion` suffix and the
     * `calendar.particular` descriptor are stripped; the astronomical block stays in the
     * comparison, so an identity overlay is proved not to perturb it either.
     *
     * @param array<string, mixed> $contract
     *
     * @return array<string, mixed>
     */
    private function liturgicalContent(array $contract): array
    {
        unset($contract['corpusVersion']);
        if (is_array($contract['calendar'])) {
            unset($contract['calendar']['particular']);
        }

        return $contract;
    }
}
