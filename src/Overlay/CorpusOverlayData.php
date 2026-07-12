<?php

declare(strict_types=1);

namespace Directorium\Core\Overlay;

use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Corpus\CorpusRecord;
use RuntimeException;

/**
 * Reads a particular-calendar {@see CalendarOverlay} from the cited CC0 corpus (#76).
 *
 * The overlays are hand-authored, cited YAML compiled by the build-time generator into
 * byte-stable NDJSON (`overlays/<slug>/operations.ndjson`) plus a metadata singleton
 * (`overlays/<slug>/overlay.json`); this loader is the read seam that rebuilds the PHP
 * {@see OverlayOperation} value objects from those rows and hands back a ready overlay.
 * Layer it over the base sanctoral with {@see OverlaidSanctoralData} and the resolver
 * produces the particular calendar — the engine stays universal, only the data changes.
 *
 * It is to overlays what {@see \Directorium\Core\Sanctoral\CorpusSanctoralData} is to the
 * base sanctoral, and shares the same typed row readers ({@see CorpusRecord}).
 */
final class CorpusOverlayData
{
    private Corpus $corpus;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
    }

    /**
     * The overlay slugs the corpus ships (e.g. `sspx`).
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return $this->corpus->overlaySlugs();
    }

    /** Whether the corpus ships an overlay with the given slug. */
    public function has(string $slug): bool
    {
        return in_array($slug, $this->slugs(), true);
    }

    /**
     * The overlay for the given slug, rebuilt from the corpus. Throws if the corpus
     * carries no such overlay.
     */
    public function overlay(string $slug): CalendarOverlay
    {
        if (!$this->has($slug)) {
            throw new RuntimeException(sprintf(
                'Corpus has no overlay "%s"; known overlays: %s.',
                $slug,
                $this->slugs() === [] ? '(none)' : implode(', ', $this->slugs())
            ));
        }

        $meta = $this->corpus->overlayMeta($slug);

        $operations = [];
        foreach ($this->corpus->overlayOperations($slug) as $row) {
            $operations[] = OverlayOperationFactory::fromRow($row);
        }

        return new CalendarOverlay(
            CorpusRecord::requireString($meta, 'id'),
            CorpusRecord::requireString($meta, 'name'),
            $operations
        );
    }
}
