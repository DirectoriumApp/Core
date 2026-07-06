<?php

declare(strict_types=1);

namespace Directorium\Core\Corpus;

use RuntimeException;

/**
 * A reader over the committed CC0 corpus (`data/corpus/`).
 *
 * The corpus is hand-authored, cited YAML compiled by the build-time generator
 * (issue #38) into byte-stable NDJSON plus a `MANIFEST.json`; the PHP engine only
 * ever *reads* it, never regenerates it. This class is that read seam: it locates
 * the corpus tree, parses its manifest and NDJSON shapes, and hands typed rows to
 * the data sources that overlay them onto the calendar (starting with
 * {@see \Directorium\Core\Sanctoral\CorpusSanctoralData}).
 *
 * Records are parsed once per file and cached for the life of the process — the
 * corpus is immutable at runtime — so constructing a data source per resolved
 * year stays cheap.
 */
final class Corpus
{
    /** @var array<string, list<array<string, mixed>>> Parsed NDJSON rows, keyed by absolute file path. */
    private static array $records = [];

    /** @var array<string, array<string, mixed>> Parsed manifests, keyed by base directory. */
    private static array $manifests = [];

    /** @var array<string, array<string, mixed>> Parsed JSON singletons, keyed by absolute file path. */
    private static array $singletons = [];

    private string $baseDir;

    private function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/\\');
    }

    /** The corpus shipped inside the package, at `data/corpus/`. */
    public static function default(): self
    {
        return new self(dirname(__DIR__, 2) . '/data/corpus');
    }

    /** A corpus rooted at an arbitrary directory — used by tests against fixtures. */
    public static function at(string $baseDir): self
    {
        return new self($baseDir);
    }

    /** The corpus root directory — a stable identity for readers that cache per corpus. */
    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * The decoded `MANIFEST.json`.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        if (!isset(self::$manifests[$this->baseDir])) {
            $path = $this->baseDir . '/MANIFEST.json';
            $decoded = json_decode($this->read($path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException(sprintf('Corpus manifest at %s is not an object.', $path));
            }
            /** @var array<string, mixed> $decoded */
            self::$manifests[$this->baseDir] = $decoded;
        }

        return self::$manifests[$this->baseDir];
    }

    /** The corpus build version stamped in the manifest. */
    public function corpusVersion(): string
    {
        $version = $this->manifest()['corpusVersion'] ?? null;
        if (!is_string($version) || $version === '') {
            throw new RuntimeException('Corpus manifest is missing a corpusVersion string.');
        }

        return $version;
    }

    /**
     * The parsed rows of an NDJSON file, addressed by its path relative to the
     * corpus root (e.g. `identity/sanctorale.ndjson`).
     *
     * @return list<array<string, mixed>>
     */
    public function records(string $relativePath): array
    {
        $path = $this->baseDir . '/' . $relativePath;
        if (isset(self::$records[$path])) {
            return self::$records[$path];
        }

        $rows = [];
        foreach (explode("\n", $this->read($path)) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException(sprintf('Corpus file %s has a non-object line.', $path));
            }
            /** @var array<string, mixed> $decoded */
            $rows[] = $decoded;
        }

        self::$records[$path] = $rows;

        return $rows;
    }

    /**
     * The edition-invariant sanctoral identity rows.
     *
     * @return list<array<string, mixed>>
     */
    public function identitySanctorale(): array
    {
        return $this->records('identity/sanctorale.ndjson');
    }

    /**
     * The per-edition sanctoral attribute rows (rank, colour).
     *
     * @return list<array<string, mixed>>
     */
    public function attributesSanctorale(string $editionDir): array
    {
        return $this->records('editions/' . $editionDir . '/attributes.sanctorale.ndjson');
    }

    /**
     * The per-edition sanctoral placement rows (month, day, vigilOf).
     *
     * @return list<array<string, mixed>>
     */
    public function placementSanctorale(string $editionDir): array
    {
        return $this->records('editions/' . $editionDir . '/placement.sanctorale.ndjson');
    }

    /**
     * The edition-invariant Easter-offset rows (slot, offset in days from Easter).
     *
     * @return list<array<string, mixed>>
     */
    public function easterOffsets(): array
    {
        return $this->records('temporal/easter-offsets.ndjson');
    }

    /**
     * The edition-invariant block->season rows.
     *
     * @return list<array<string, mixed>>
     */
    public function blockSeasons(): array
    {
        return $this->records('temporal/block-seasons.ndjson');
    }

    /**
     * The edition-invariant temporal identity rows (archetype, kind, name template).
     *
     * @return list<array<string, mixed>>
     */
    public function identityTemporale(): array
    {
        return $this->records('identity/temporale.ndjson');
    }

    /**
     * The per-edition temporal attribute rows (archetype, rank, colour).
     *
     * @return list<array<string, mixed>>
     */
    public function attributesTemporale(string $editionDir): array
    {
        return $this->records('editions/' . $editionDir . '/attributes.temporale.ndjson');
    }

    /**
     * The per-edition precedence tier rows (selector, line, ordinal, subOrder).
     *
     * @return list<array<string, mixed>>
     */
    public function precedenceTiers(string $editionDir): array
    {
        return $this->records('editions/' . $editionDir . '/precedence-tiers.ndjson');
    }

    /**
     * The per-edition precedence rule rows (membership id-sets and commemoration
     * limits — a heterogeneous list discriminated by the `rule` field).
     *
     * @return list<array<string, mixed>>
     */
    public function precedenceRules(string $editionDir): array
    {
        return $this->records('editions/' . $editionDir . '/precedence-rules.ndjson');
    }

    /**
     * The particular-calendar overlay slugs the corpus ships, from the manifest
     * (Core #76). Empty when the corpus carries no overlays.
     *
     * @return list<string>
     */
    public function overlaySlugs(): array
    {
        $overlays = $this->manifest()['overlays'] ?? [];
        if (!is_array($overlays)) {
            throw new RuntimeException('Corpus manifest "overlays" is not a list.');
        }

        $out = [];
        foreach ($overlays as $slug) {
            if (!is_string($slug)) {
                throw new RuntimeException('Corpus manifest "overlays" has a non-string entry.');
            }
            $out[] = $slug;
        }

        return $out;
    }

    /**
     * An overlay's metadata singleton (`overlays/<slug>/overlay.json`): its URN,
     * display name, rite, and operation count.
     *
     * @return array<string, mixed>
     */
    public function overlayMeta(string $slug): array
    {
        return $this->singleton('overlays/' . $slug . '/overlay.json');
    }

    /**
     * An overlay's operation rows (`overlays/<slug>/operations.ndjson`), sorted by
     * target id — one add / suppress / rerank per row.
     *
     * @return list<array<string, mixed>>
     */
    public function overlayOperations(string $slug): array
    {
        return $this->records('overlays/' . $slug . '/operations.ndjson');
    }

    /**
     * A decoded JSON-object singleton file, addressed by its path relative to the
     * corpus root, parsed once and cached for the life of the process.
     *
     * @return array<string, mixed>
     */
    private function singleton(string $relativePath): array
    {
        $path = $this->baseDir . '/' . $relativePath;
        if (isset(self::$singletons[$path])) {
            return self::$singletons[$path];
        }

        $decoded = json_decode($this->read($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Corpus file %s is not a JSON object.', $path));
        }
        /** @var array<string, mixed> $decoded */
        self::$singletons[$path] = $decoded;

        return $decoded;
    }

    private function read(string $path): string
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf(
                'Corpus file not found: %s. Regenerate it with the corpus generator (tools/generator).',
                $path
            ));
        }

        return $contents;
    }
}
