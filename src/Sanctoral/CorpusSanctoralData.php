<?php

declare(strict_types=1);

namespace Introibo\Core\Sanctoral;

use Introibo\Core\Attribute\Colour;
use Introibo\Core\Attribute\ElementColour;
use Introibo\Core\Attribute\RankClass;
use Introibo\Core\Citation\CitationSet;
use Introibo\Core\Corpus\Corpus;
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
    /** The path-safe directory key for the 1962 edition inside the corpus tree. */
    private const EDITION_DIR = 'roman-rubricae-1960';

    private Corpus $corpus;

    public function __construct(?Corpus $corpus = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
    }

    public function version(): string
    {
        return $this->corpus->corpusVersion();
    }

    /** @return list<SanctoralEntry> */
    public function entries(): array
    {
        $identityById = $this->indexById($this->corpus->identitySanctorale());
        $attributesById = $this->indexById($this->corpus->attributesSanctorale(self::EDITION_DIR));

        $entries = [];
        foreach ($this->corpus->placementSanctorale(self::EDITION_DIR) as $placement) {
            $id = $this->requireString($placement, 'id');
            $identity = $identityById[$id] ?? null;
            $attributes = $attributesById[$id] ?? null;
            if ($identity === null || $attributes === null) {
                throw new RuntimeException(sprintf(
                    'Corpus is missing an identity or attributes row for placed observance "%s".',
                    $id
                ));
            }

            $vigilOf = $this->optionalString($placement, 'vigilOf');

            $entries[] = new SanctoralEntry(
                $this->requireInt($placement, 'month'),
                $this->requireInt($placement, 'day'),
                new Observance(
                    ObservanceId::parse($id),
                    ObservanceKind::fromString($this->requireString($identity, 'kind')),
                    $this->titulars($identity),
                    $this->names($identity)
                ),
                RankClass::fromOrdinal($this->requireInt($attributes, 'rank')),
                $this->elementColour($attributes),
                $vigilOf !== null ? ObservanceId::parse($vigilOf) : null,
                CitationSet::fromMarkers(array_merge(
                    $this->cites($identity),
                    $this->cites($attributes),
                    $this->cites($placement)
                ))
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
            $indexed[$this->requireString($row, 'id')] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function elementColour(array $attributes): ElementColour
    {
        $colour = $attributes['colour'] ?? null;
        if (!is_array($colour) || !isset($colour['base']) || !is_string($colour['base'])) {
            throw new RuntimeException(sprintf(
                'Corpus attribute %s has no colour base.',
                $this->requireString($attributes, 'id')
            ));
        }

        if (($colour['roseAllowed'] ?? false) === true) {
            return ElementColour::violetWithRose();
        }

        return ElementColour::of(Colour::fromString($colour['base']));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return non-empty-list<string>
     */
    private function titulars(array $row): array
    {
        $titulars = $row['titulars'] ?? null;
        if (!is_array($titulars) || $titulars === []) {
            throw new RuntimeException(
                sprintf('Corpus identity %s has no titulars.', $this->requireString($row, 'id'))
            );
        }

        $out = [];
        foreach ($titulars as $titular) {
            if (!is_string($titular)) {
                throw new RuntimeException(sprintf('Corpus identity %s has a non-string titular.', $row['id']));
            }
            $out[] = $titular;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function names(array $row): array
    {
        $names = $row['names'] ?? null;
        if (!is_array($names)) {
            throw new RuntimeException(sprintf('Corpus identity %s has no names.', $this->requireString($row, 'id')));
        }

        $out = [];
        foreach ($names as $locale => $label) {
            if (!is_string($locale) || !is_string($label)) {
                throw new RuntimeException(sprintf('Corpus identity %s has a malformed name entry.', $row['id']));
            }
            $out[$locale] = $label;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>
     */
    private function cites(array $row): array
    {
        $cites = $row['cites'] ?? [];
        if (!is_array($cites)) {
            throw new RuntimeException('Corpus record has a malformed cites map.');
        }

        $out = [];
        foreach ($cites as $field => $ref) {
            if (!is_string($field) || !is_string($ref)) {
                throw new RuntimeException('Corpus record has a malformed cites entry.');
            }
            $out[$field] = $ref;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Corpus record is missing string field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException(sprintf('Corpus record is missing integer field "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function optionalString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Corpus record has a malformed optional field "%s".', $key));
        }

        return $value;
    }
}
