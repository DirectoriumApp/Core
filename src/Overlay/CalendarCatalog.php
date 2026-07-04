<?php

declare(strict_types=1);

namespace Introibo\Core\Overlay;

use Introibo\Core\Contract\CalendarDescriptor;
use Introibo\Core\Corpus\Corpus;
use Introibo\Core\Edition\RubricSystem;
use Introibo\Core\Precedence\DayResolver;
use Introibo\Core\Sanctoral\CorpusSanctoralData;
use InvalidArgumentException;

/**
 * The calendar the engine resolves under: the universal 1962 base, or one of the
 * particular calendars the corpus ships as overlays (#78).
 *
 * This is the seam behind the `$calendar` selector on {@see \Introibo\Core\day()} and
 * {@see \Introibo\Core\contract()}. Given a selector — `null` for the universal 1962
 * calendar, or a particular calendar named by its slug (`sspx`) or full overlay URN
 * (`introibo:overlay:roman:sspx`) — it builds the right {@see DayResolver} (layering
 * the overlay onto the base sanctoral via {@see OverlaidSanctoralData}) and the
 * {@see CalendarDescriptor} the output contract stamps. The engine itself is
 * unchanged: selecting a calendar only chooses which sanctoral data the resolver reads.
 */
final class CalendarCatalog
{
    private Corpus $corpus;

    private CorpusOverlayData $overlays;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
        $this->overlays = new CorpusOverlayData($this->corpus);
    }

    /**
     * The resolver for the selected calendar and rubric system: the universal base when
     * `$calendar` is null, or the engine under that particular-calendar overlay, resolved
     * under `$rubricSystem` (null = the default 1962 edition).
     *
     * The two selectors are orthogonal: `$rubricSystem` chooses the rules-family and its
     * edition data; `$calendar` layers a particular calendar over it. When `$rubricSystem`
     * is null the engine resolves under 1962 exactly as before.
     */
    public function resolver(?string $calendar, ?string $rubricSystem = null): DayResolver
    {
        $system = RubricSystem::fromString($rubricSystem);
        $base = new CorpusSanctoralData($this->corpus, $system->corpusDir());
        if ($calendar === null) {
            return DayResolver::forEdition($system, $base);
        }

        return DayResolver::forEdition($system, new OverlaidSanctoralData($base, $this->overlay($calendar)));
    }

    /**
     * The contract `calendar`-block descriptor for the selected calendar, or null for
     * the universal 1962 base (whose contract keeps the block null).
     */
    public function descriptor(?string $calendar): ?CalendarDescriptor
    {
        if ($calendar === null) {
            return null;
        }

        $overlay = $this->overlay($calendar);

        return new CalendarDescriptor($overlay->id(), $overlay->name());
    }

    /**
     * The particular calendars the corpus ships, by slug — the selectors accepted
     * besides the universal `null`.
     *
     * @return list<string>
     */
    public function particularCalendars(): array
    {
        return $this->overlays->slugs();
    }

    /**
     * The overlay for a selector accepted as either its slug (`sspx`) or its full
     * platform URN (`introibo:overlay:roman:sspx`).
     */
    private function overlay(string $calendar): CalendarOverlay
    {
        foreach ($this->overlays->slugs() as $slug) {
            if ($slug === $calendar) {
                return $this->overlays->overlay($slug);
            }
            $overlay = $this->overlays->overlay($slug);
            if ($overlay->id() === $calendar) {
                return $overlay;
            }
        }

        $known = $this->overlays->slugs();
        throw new InvalidArgumentException(sprintf(
            'Unknown calendar "%s". Select null for the universal 1962 calendar%s.',
            $calendar,
            $known === [] ? '' : ', or one of: ' . implode(', ', $known)
        ));
    }
}
