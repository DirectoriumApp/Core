<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Contract\CalendarDescriptor;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Precedence\DayResolver;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use InvalidArgumentException;

/**
 * The calendar the engine resolves under: the universal 1962 base, or one of the
 * particular calendars the corpus ships as overlays (#78).
 *
 * This is the seam behind the `$calendar` selector on {@see \Directorium\Core\day()} and
 * {@see \Directorium\Core\contract()}. Given a selector — `null` for the universal 1962
 * calendar, or a particular calendar named by its slug (`sspx`) or full overlay URN
 * (`directorium:overlay:roman:sspx`) — it builds the right {@see DayResolver} (layering
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
        // A rubric system declared on the edition axis but not yet built is refused at this
        // public boundary. The four current systems (1962, 1954, 1955, and Novus Ordo 2002) are
        // built (#453 / #256), so this gate does not fire for them; it stands for the reserved
        // editions still on the axis — the 1969/1975 Novus-Ordo snapshots, and a future
        // Tridentine — which sit on the axis before their data and rules exist and must not
        // resolve an incomplete calendar. Built systems are advertised via
        // RubricSystem::isBuilt() (the Api /meta filter); this is the matching runtime gate.
        if (!$system->isBuilt()) {
            throw new InvalidArgumentException(sprintf(
                'The %s rubric system is declared but not yet built; its calendar is not resolvable. '
                . 'Select a built edition (the default is 1962 / Rubricae 1960).',
                $system->label()
            ));
        }
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
     * platform URN (`directorium:overlay:roman:sspx`).
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
