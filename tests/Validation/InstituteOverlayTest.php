<?php

declare(strict_types=1);

namespace Directorium\Core\Tests\Validation;

use Directorium\Core\Contract\DayContract;
use Directorium\Core\Overlay\CalendarCatalog;
use Directorium\Core\Overlay\CorpusOverlayData;
use Directorium\Core\Temporal\TemporalCalendar;
use PHPUnit\Framework\TestCase;

/**
 * Validation harness (#45) — the FSSP and ICKSP particular-calendar conformance gates
 * (#81 / #82 / #87).
 *
 * These two societies keep a particular calendar: the universal 1962 base plus their own
 * proper elevations. Unlike SSPX (#80), which is cross-checked against a live published feed,
 * the FSSP and ICKSP proper days are pinned as small hand-curated fixtures
 * ({@see fixtures/fssp}, {@see fixtures/icksp}) sourced from each society's own published
 * calendar/ordo — each row a fixed-date proper feast, pinned to a specific civil year in which
 * it is actually celebrated, with the class the society assigns it. The gate resolves the
 * engine **under the overlay** ({@see CalendarCatalog}) and asserts it reproduces that class,
 * and — separately — that the overlay actually changes the day (the base calendar resolves the
 * date differently). A broken overlay, a wrong target id, or a regression fails here.
 *
 * The fixtures are a NON-EXHAUSTIVE, well-attested subset of each society's proper calendar,
 * not the complete promulgated Proprium; see each overlay's YAML scope note and provenance.json.
 *
 * @group overlays
 */
final class InstituteOverlayTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures';

    /** Overlay slug => the operation count the overlay ships (its proper-elevation count). */
    private const OVERLAYS = [
        'fssp' => 1,
        'icksp' => 4,
    ];

    /**
     * Each society is advertised as a selectable calendar with the expected proper-elevation
     * operation count and a contract descriptor naming it.
     *
     * @dataProvider overlays
     */
    public function testEachOverlayIsAdvertisedWithItsProperElevations(string $slug, int $operations): void
    {
        $catalog = new CalendarCatalog();
        self::assertContains($slug, $catalog->particularCalendars(), "$slug must be a selectable calendar.");

        $descriptor = $catalog->descriptor($slug);
        self::assertNotNull($descriptor);
        self::assertSame("directorium:overlay:roman:$slug", $descriptor->id());
        self::assertNotSame('', $descriptor->name());

        $overlay = (new CorpusOverlayData())->overlay($slug);
        self::assertCount($operations, $overlay->operations(), "$slug must ship $operations proper elevation(s).");
    }

    /**
     * The conformance gate: on each pinned proper day, the engine under the society's overlay
     * celebrates that society's feast at the class it publishes — and the base calendar resolves
     * the day differently, so the elevation is proved to be the overlay's doing.
     *
     * @dataProvider properDays
     */
    public function testOverlayResolvesEachProperDayAtTheElevatedClass(
        string $slug,
        string $date,
        string $id,
        string $name,
        int $class
    ): void {
        $catalog = new CalendarCatalog();
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $dt = TemporalCalendar::utcDate($y, $m, $d);

        $overlaid = $catalog->resolver($slug)->resolveYear($y);
        $overlaidDay = DayContract::from($overlaid->day($dt), $overlaid->provenance())->toArray();
        $celebration = $overlaidDay['celebration'][0] ?? null;
        self::assertNotNull($celebration, "$slug $date ($name): the overlay produced no celebration.");
        self::assertSame($id, $celebration['id'], "$slug $date: expected $name to be the celebration.");
        self::assertSame($class, $celebration['rankOrdinal'], "$slug $date: expected $name at class $class.");

        // The elevation is the overlay's doing: the universal calendar resolves the day otherwise.
        $base = $catalog->resolver(null)->resolveYear($y);
        $baseCeleb = DayContract::from($base->day($dt), $base->provenance())->toArray()['celebration'][0] ?? null;
        self::assertNotNull($baseCeleb);
        self::assertNotSame(
            [$id, $class],
            [$baseCeleb['id'], $baseCeleb['rankOrdinal']],
            "$slug $date: the overlay must change the day, but the base calendar resolves it identically."
        );
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public function overlays(): iterable
    {
        foreach (self::OVERLAYS as $slug => $operations) {
            yield $slug => [$slug, $operations];
        }
    }

    /**
     * @return iterable<string, array{string, string, string, string, int}>
     */
    public function properDays(): iterable
    {
        foreach (array_keys(self::OVERLAYS) as $slug) {
            $path = self::FIXTURE_DIR . '/' . $slug . '/proper-days.ndjson';
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new \RuntimeException("Missing proper-days fixture for $slug.");
            }
            foreach (array_filter(explode("\n", trim($raw))) as $line) {
                /** @var array{date: string, id: string, name: string, class: int, source: string} $row */
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                yield $slug . ' ' . $row['date'] => [$slug, $row['date'], $row['id'], $row['name'], $row['class']];
            }
        }
    }
}
