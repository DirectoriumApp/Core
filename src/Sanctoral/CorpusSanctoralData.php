<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\LegacyRank;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Citation\CitationSet;
use Introibo\Core\Corpus\Corpus;
use Introibo\Core\Corpus\CorpusRecord;
use Introibo\Core\Observance\Observance;
use Introibo\Core\Observance\ObservanceId;
use Introibo\Core\Observance\ObservanceKind;
use RuntimeException;

/**
 * The 1962 fixed-date sanctoral, read from the cited CC0 corpus.
 *
 * This is the production {@see SanctoralData}: it joins the corpus's three
 * sanctoral shapes — the edition-invariant identity, and the 1962 edition's
 * attributes (rank, colour) and placement (month, day, vigilOf) — by observance
 * id, and rebuilds each {@see SanctoralEntry}, carrying the per-datum
 * {@see CitationSet}. It replaces the provisional seed source without touching the
 * overlay loader ({@see SanctoralCalendar}), which is exactly the swap the
 * {@see SanctoralData} seam was built for. The corpus version stamped on the
 * output contract (#52) comes straight from the manifest.
 */
final class CorpusSanctoralData implements SanctoralData
{
    /** The path-safe directory key for the default (1962) edition inside the corpus tree. */
    public const DEFAULT_EDITION_DIR = 'roman-rubricae-1960';

    private Corpus $corpus;

    /** The edition subdirectory whose per-edition attributes and placement this reads. */
    private string $editionDir;

    public function __construct(?Corpus $corpus = null, string $editionDir = self::DEFAULT_EDITION_DIR)
    {
        $this->corpus = $corpus ?? Corpus::default();
        $this->editionDir = $editionDir;
    }

    public function version(): string
    {
        return $this->corpus->corpusVersion();
    }

    /** @return list<SanctoralEntry> */
    public function entries(): array
    {
        $identityById = $this->indexById($this->corpus->identitySanctorale());
        $attributesById = $this->indexById($this->corpus->attributesSanctorale($this->editionDir));

        $entries = [];
        foreach ($this->corpus->placementSanctorale($this->editionDir) as $placement) {
            $id = CorpusRecord::requireString($placement, 'id');
            $identity = $identityById[$id] ?? null;
            $attributes = $attributesById[$id] ?? null;
            if ($identity === null || $attributes === null) {
                throw new RuntimeException(sprintf(
                    'Corpus is missing an identity or attributes row for placed observance "%s".',
                    $id
                ));
            }

            $vigilOf = CorpusRecord::optionalString($placement, 'vigilOf');
            $octaveOf = CorpusRecord::optionalString($placement, 'octaveOf');
            $legacyRank = CorpusRecord::optionalString($attributes, 'legacyRank');

            $entries[] = new SanctoralEntry(
                CorpusRecord::requireInt($placement, 'month'),
                CorpusRecord::requireInt($placement, 'day'),
                new Observance(
                    ObservanceId::parse($id),
                    ObservanceKind::fromString(CorpusRecord::requireString($identity, 'kind')),
                    CorpusRecord::titulars($identity),
                    CorpusRecord::names($identity)
                ),
                RankClass::fromOrdinal(CorpusRecord::requireInt($attributes, 'rank')),
                CorpusRecord::elementColour($attributes),
                $vigilOf !== null ? ObservanceId::parse($vigilOf) : null,
                CitationSet::fromMarkers(array_merge(
                    CorpusRecord::cites($identity),
                    CorpusRecord::cites($attributes),
                    CorpusRecord::cites($placement)
                )),
                $legacyRank !== null ? LegacyRank::fromString($legacyRank) : null,
                $octaveOf !== null ? ObservanceId::parse($octaveOf) : null
            );
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[CorpusRecord::requireString($row, 'id')] = $row;
        }

        return $indexed;
    }
}
