<?php

declare(strict_types=1);

namespace Directorium\Core\Edition;

use InvalidArgumentException;
use RuntimeException;

/**
 * A rubric system (edition): the frozen rules-family + calendar snapshot the engine
 * resolves under — the 1954 (Divino Afflatu), 1955 (interim / Cum nostra hac aetate),
 * or 1962 (Rubricae 1960) system.
 *
 * This is the edition axis of {@see \Directorium\Core\day()} / {@see \Directorium\Core\contract()},
 * and it is **orthogonal to the particular-calendar overlay axis** ($calendar): an edition
 * names *which rubrics* govern (its precedence rules + its `data/corpus/editions/<dir>/`
 * data), while an overlay (SSPX, FSSP) is a layer resolved *over* an edition
 * (docs/design/edition-governance.md: "an overlay is never an edition"). The default is
 * {@see rubricae1960()}, so every caller that omits the selector resolves under 1962 exactly
 * as before.
 *
 * A system carries its stable **edition URN** (stamped into the output contract's
 * {@see \Directorium\Core\Contract\Provenance}), its **corpus directory**, a display **label**,
 * and its historical **validity window**. The three traditional systems — 1962 (Rubricae 1960),
 * 1954 (Divino Afflatu) and 1955 (Cum nostra) — are built and publicly resolvable (Epics #63 /
 * #68, via the #453 burndown). The **Novus Ordo** (Ordinary Form) is declared on the axis as a
 * living calendar of *editio typica* snapshots (docs/design/novus-ordo-calendar-model.md,
 * docs/design/edition-governance.md): `roman:novus-ordo-2002` is the build target of v1.1 and the
 * 1969/1975 snapshots are reserved. Until each is built the {@see isBuilt()} flag keeps it
 * refused at the public boundary — reachable only through {@see \Directorium\Core\Precedence\DayResolver::forEdition()}
 * for its own build's tests. See docs/design/rubric-system-model.md.
 */
final class RubricSystem
{
    /** The 1954 Divino Afflatu system (pre-1955): full octaves, vigils, and the double/semidouble ranks. */
    public const DIVINO_AFFLATU = 'roman:divino-afflatu';

    /** The 1955 interim system (Cum nostra hac aetate): three octaves, seven vigils, semidouble suppressed. */
    public const RUBRICAE_1955 = 'roman:rubricae-1955';

    /** The 1962 system (Rubricae 1960 / editio typica 1962): the four-class scheme. The built default. */
    public const RUBRICAE_1960 = 'roman:rubricae-1960';

    /**
     * The Novus Ordo (Ordinary Form) *editio typica tertia* 2002/2008 — the current General
     * Roman Calendar (solemnity/feast/memorial ranks, Ordinary Time, the Table of Liturgical
     * Days). A living calendar: this snapshot carries the roster to date, and dated decrees
     * accrue as overlays (docs/design/edition-governance.md).
     */
    public const NOVUS_ORDO_2002 = 'roman:novus-ordo-2002';

    /** The Novus Ordo *editio typica* 1969 — reserved (declared, not yet built). */
    public const NOVUS_ORDO_1969 = 'roman:novus-ordo-1969';

    /** The Novus Ordo *editio typica altera* 1975 — reserved (declared, not yet built). */
    public const NOVUS_ORDO_1975 = 'roman:novus-ordo-1975';

    private string $urn;

    private string $corpusDir;

    private string $label;

    private int $validFrom;

    private ?int $validTo;

    private bool $built;

    private string $discipline;

    private function __construct(
        string $urn,
        string $corpusDir,
        string $label,
        int $validFrom,
        ?int $validTo,
        bool $built,
        string $discipline
    ) {
        $this->urn = $urn;
        $this->corpusDir = $corpusDir;
        $this->label = $label;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
        $this->built = $built;
        $this->discipline = $discipline;
    }

    /**
     * The rubric systems the platform knows, in historical order. The three traditional
     * systems are built and resolvable (Epics #63 / #68, via the #453 burndown); the Novus
     * Ordo snapshots are declared with `built => false` — `roman:novus-ordo-2002` flips true
     * when v1.1 completes, the 1969/1975 snapshots stay reserved. The `built` flag lets a
     * system be declared on the axis before its data and rules land.
     *
     * @return array<string, array{dir: string, label: string, from: int, to: int|null,
     *     built: bool, discipline: string}>
     */
    private static function registry(): array
    {
        return [
            self::DIVINO_AFFLATU => [
                'dir' => 'roman-divino-afflatu',
                'label' => 'Divino Afflatu (1954)',
                'from' => 1913,
                'to' => 1955,
                'built' => true,
                'discipline' => 'cic-1917',
            ],
            self::RUBRICAE_1955 => [
                'dir' => 'roman-rubricae-1955',
                'label' => 'Interim rubrics (1955)',
                'from' => 1956,
                'to' => 1960,
                'built' => true,
                'discipline' => 'cic-1917',
            ],
            self::RUBRICAE_1960 => [
                'dir' => 'roman-rubricae-1960',
                'label' => 'Rubricae 1960 (1962)',
                'from' => 1961,
                'to' => null,
                'built' => true,
                'discipline' => 'cic-1917',
            ],
            self::NOVUS_ORDO_1969 => [
                'dir' => 'roman-novus-ordo-1969',
                'label' => 'Novus Ordo (1969 editio typica)',
                'from' => 1970,
                'to' => 1974,
                'built' => false,
                'discipline' => 'cic-1983',
            ],
            self::NOVUS_ORDO_1975 => [
                'dir' => 'roman-novus-ordo-1975',
                'label' => 'Novus Ordo (1975 editio typica altera)',
                'from' => 1975,
                'to' => 2001,
                'built' => false,
                'discipline' => 'cic-1983',
            ],
            self::NOVUS_ORDO_2002 => [
                'dir' => 'roman-novus-ordo-2002',
                'label' => 'Novus Ordo (2002 editio typica tertia)',
                'from' => 2002,
                'to' => null,
                'built' => false,
                'discipline' => 'cic-1983',
            ],
        ];
    }

    /** The default system when a caller names none: 1962 (Rubricae 1960). */
    public static function default(): self
    {
        return self::rubricae1960();
    }

    public static function rubricae1960(): self
    {
        return self::of(self::RUBRICAE_1960);
    }

    public static function divinoAfflatu(): self
    {
        return self::of(self::DIVINO_AFFLATU);
    }

    public static function rubricae1955(): self
    {
        return self::of(self::RUBRICAE_1955);
    }

    /**
     * The system named by a selector: `null` for the default (1962), an edition URN
     * (`roman:rubricae-1960`), or a friendly alias (`1962`, `1960`, `1954`,
     * `divino-afflatu`, `1955`, `novus-ordo`, `ordinary-form`, `2002`). A declared-but-unbuilt
     * system (the Novus Ordo snapshots) resolves here but is refused at the public boundary.
     */
    public static function fromString(?string $selector): self
    {
        if ($selector === null || $selector === '') {
            return self::default();
        }

        $urn = self::ALIASES[$selector] ?? $selector;
        if (!isset(self::registry()[$urn])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown rubric system "%s"; valid values: %s (or an alias: %s).',
                $selector,
                implode(', ', array_keys(self::registry())),
                implode(', ', array_keys(self::ALIASES))
            ));
        }

        return self::of($urn);
    }

    /** Friendly aliases accepted by {@see fromString()} besides the full edition URNs. */
    private const ALIASES = [
        '1962' => self::RUBRICAE_1960,
        '1960' => self::RUBRICAE_1960,
        'rubricae-1960' => self::RUBRICAE_1960,
        '1954' => self::DIVINO_AFFLATU,
        'divino-afflatu' => self::DIVINO_AFFLATU,
        '1955' => self::RUBRICAE_1955,
        'rubricae-1955' => self::RUBRICAE_1955,
        'novus-ordo' => self::NOVUS_ORDO_2002,
        'ordinary-form' => self::NOVUS_ORDO_2002,
        '2002' => self::NOVUS_ORDO_2002,
        '1975' => self::NOVUS_ORDO_1975,
        '1969' => self::NOVUS_ORDO_1969,
    ];

    /**
     * Every declared system, for discovery (the Api's `/meta`). Includes systems not
     * yet built; filter on {@see isBuilt()} to advertise only the resolvable ones.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        $systems = [];
        foreach (array_keys(self::registry()) as $urn) {
            $systems[] = self::of($urn);
        }

        return $systems;
    }

    private static function of(string $urn): self
    {
        $meta = self::registry()[$urn] ?? null;
        if ($meta === null) {
            throw new RuntimeException(sprintf('No rubric system registered for "%s".', $urn));
        }

        return new self(
            $urn,
            $meta['dir'],
            $meta['label'],
            $meta['from'],
            $meta['to'],
            $meta['built'],
            $meta['discipline']
        );
    }

    /** The stable edition URN stamped into the output contract, e.g. `roman:rubricae-1960`. */
    public function urn(): string
    {
        return $this->urn;
    }

    /** The path-safe directory key of this edition inside `data/corpus/editions/`. */
    public function corpusDir(): string
    {
        return $this->corpusDir;
    }

    /** A human-readable label, e.g. `Rubricae 1960 (1962)`. */
    public function label(): string
    {
        return $this->label;
    }

    /** The first civil year the edition governed. */
    public function validFrom(): int
    {
        return $this->validFrom;
    }

    /** The last civil year the edition governed, or null for an edition still in traditional use. */
    public function validTo(): ?int
    {
        return $this->validTo;
    }

    /**
     * Whether a year falls inside the edition's historical validity window. Resolving a
     * date outside it is anachronistic and is flagged, not refused (docs/design/edition-governance.md).
     */
    public function governs(int $year): bool
    {
        return $year >= $this->validFrom && ($this->validTo === null || $year <= $this->validTo);
    }

    /** Whether this system's data and precedence rules are built and resolvable today. */
    public function isBuilt(): bool
    {
        return $this->built;
    }

    /**
     * The penitential-discipline key this edition resolves under (#248) — the corpus
     * `disciplines/<key>/` folder whose fast/abstinence law governs. Fasting is canon
     * law, not rubric, so every traditional edition (1954/1955/1962) points at the same
     * 1917 Code (`cic-1917`); a later era on a different discipline changes only this key.
     */
    public function penitentialDiscipline(): string
    {
        return $this->discipline;
    }

    public function equals(self $other): bool
    {
        return $this->urn === $other->urn;
    }

    public function __toString(): string
    {
        return $this->urn;
    }
}
