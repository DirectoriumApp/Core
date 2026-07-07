<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use Directorium\Core\Attribute\Colour;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceKind;
use RuntimeException;

/**
 * The read seam for the temporal ARCHETYPE overlay (#42): the per-office kind,
 * rank, colour, and Latin name template that the six temporal block-fillers used
 * to hold as literals.
 *
 * It joins the corpus's two temporal shapes — the edition-invariant identity
 * (kind + name template) and the 1962 edition's attributes (rank + colour) — by
 * the archetype key, and hands each filler a {@see TemporalArchetype}. The date
 * arithmetic and the naming grammar stay in the engine; the office facts are now
 * data, so a later rules-family can vary them without touching the fillers — the
 * same swap the {@see TemporalDefinitions} skeleton seam was built for.
 *
 * The archetype map is memoised for the life of the process (the corpus is
 * immutable at runtime), keyed by the reader's identity.
 */
final class TemporalAttributes
{
    /** The path-safe directory key for the base (1962) edition inside the corpus tree. */
    private const EDITION_DIR = 'roman-rubricae-1960';

    /** @var array<string, array<string, TemporalArchetype>> Archetype maps, keyed by corpus base + edition. */
    private static array $archetypes = [];

    private Corpus $corpus;

    /** The edition whose temporal attributes this reader overlays (the corpus dir key). */
    private string $editionDir;

    private string $cacheKey;

    public function __construct(?Corpus $corpus = null, ?string $editionDir = null)
    {
        $this->corpus = $corpus ?? Corpus::default();
        $this->editionDir = $editionDir ?? self::EDITION_DIR;
        $this->cacheKey = spl_object_hash($this->corpus) . ':' . $this->editionDir;
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * Whether the edition declares a temporal archetype — the edition-aware gate the
     * season-fillers use to mint an office ONLY where the edition keeps it (the 1954
     * privileged temporal octaves: Epiphany, Corpus Christi, Ascension, Sacred Heart).
     * The base (1962) edition declares none of the additional archetypes, so its fillers
     * mint nothing extra and its resolution — and the golden fixture — stays byte-identical
     * by construction.
     */
    public function has(string $key): bool
    {
        return isset($this->archetypes()[$key]);
    }

    /**
     * The office facts of one archetype.
     *
     * @throws RuntimeException if the archetype is not defined in the corpus
     */
    public function archetype(string $key): TemporalArchetype
    {
        $archetypes = $this->archetypes();
        if (!isset($archetypes[$key])) {
            throw new RuntimeException(sprintf('Corpus has no temporal archetype "%s".', $key));
        }

        return $archetypes[$key];
    }

    /**
     * @return array<string, TemporalArchetype>
     */
    private function archetypes(): array
    {
        if (isset(self::$archetypes[$this->cacheKey])) {
            return self::$archetypes[$this->cacheKey];
        }

        $identityByKey = [];
        foreach ($this->corpus->identityTemporale() as $row) {
            $identityByKey[$this->requireString($row, 'archetype')] = $row;
        }

        $map = [];
        foreach ($this->corpus->attributesTemporale($this->editionDir) as $attributes) {
            $key = $this->requireString($attributes, 'archetype');
            $identity = $identityByKey[$key] ?? null;
            if ($identity === null) {
                throw new RuntimeException(sprintf(
                    'Corpus temporal attributes for "%s" have no identity row.',
                    $key
                ));
            }

            $map[$key] = new TemporalArchetype(
                ObservanceKind::fromString($this->requireString($identity, 'kind')),
                RankClass::fromOrdinal($this->requireInt($attributes, 'rank')),
                $this->elementColour($attributes),
                $this->nameTemplate($identity)
            );
        }

        self::$archetypes[$this->cacheKey] = $map;

        return $map;
    }

    /**
     * @param array<string, mixed> $identity
     */
    private function nameTemplate(array $identity): string
    {
        $names = $identity['names'] ?? null;
        if (!is_array($names) || !isset($names['la']) || !is_string($names['la']) || $names['la'] === '') {
            throw new RuntimeException(sprintf(
                'Corpus temporal identity "%s" has no Latin name template.',
                $this->requireString($identity, 'archetype')
            ));
        }

        return $names['la'];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function elementColour(array $attributes): ElementColour
    {
        $colour = $attributes['colour'] ?? null;
        if (!is_array($colour) || !isset($colour['base']) || !is_string($colour['base'])) {
            throw new RuntimeException(sprintf(
                'Corpus temporal attributes "%s" have no colour base.',
                $this->requireString($attributes, 'archetype')
            ));
        }

        if (($colour['roseAllowed'] ?? false) === true) {
            return ElementColour::violetWithRose();
        }

        return ElementColour::of(Colour::fromString($colour['base']));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function requireString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Corpus temporal record is missing string field "%s".', $key));
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
            throw new RuntimeException(sprintf('Corpus temporal record is missing integer field "%s".', $key));
        }

        return $value;
    }
}
