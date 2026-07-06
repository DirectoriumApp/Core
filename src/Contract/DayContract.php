<?php

declare(strict_types=1);

namespace Directorium\Core\Contract;

use DateTimeImmutable;
use Directorium\Core\Calendar\CelebrationRole;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Calendar\RoledObservance;
use Directorium\Core\Calendrical\CalendricalYear;
use Directorium\Core\Calendrical\LunarAge;
use Directorium\Core\Precedence\ConcurrenceOutcome;
use Directorium\Core\Sanctoral\SanctoralObservance;
use Directorium\Core\Temporal\TemporalObservance;
use LogicException;

/**
 * The versioned, serialisable shape of a resolved {@see LiturgicalDay} — the
 * public output contract every other Directorium repo (Api/Site/Ordo) builds on.
 *
 * {@see LiturgicalDay} is kept a pure aggregate; this class is the seam that
 * turns it into a stable JSON-ready structure. The shape is frozen at
 * {@see SHAPE_VERSION} 1.0.0: a day carries its three provenance axes
 * ({@see Provenance}) and the four office roles, each office a self-describing
 * record of identity, per-edition attributes, occurrence outcome, and transfer
 * links. The `calendar` block carries the calendrical/astronomical figures (#242) and
 * `fasting` the penitential obligation (#250); the remaining reserved slots
 * (`firstVespers`, `resolution`, and the office-level
 * `octaveOf`/`aliases`/`citations`/`text`/`chant`/`audio`) are emitted as null now and
 * only ever filled later, so the contract grows additively. Serialisation is
 * deterministic: same inputs, byte-identical JSON. See docs/design/output-contract.md.
 */
final class DayContract
{
    /** SemVer of the contract *shape* (distinct from the corpus and engine versions). */
    public const SHAPE_VERSION = '1.0.2';

    private LiturgicalDay $day;

    private Provenance $provenance;

    private ?CalendarDescriptor $calendar;

    private function __construct(LiturgicalDay $day, Provenance $provenance, ?CalendarDescriptor $calendar)
    {
        $this->day = $day;
        $this->provenance = $provenance;
        $this->calendar = $calendar;
    }

    /**
     * @param CalendarDescriptor|null $calendar the particular calendar the day was
     *        resolved under (#78), or null for the universal 1962 calendar
     */
    public static function from(
        LiturgicalDay $day,
        Provenance $provenance,
        ?CalendarDescriptor $calendar = null
    ): self {
        return new self($day, $provenance, $calendar);
    }

    /**
     * The day as a JSON-ready associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->dayToArray();
    }

    /**
     * The day serialised to JSON with the frozen flags: unescaped Unicode (Latin
     * names read cleanly), unescaped slashes (dates and ids stay legible), and
     * throw-on-error (never a silent `false`).
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dayToArray(): array
    {
        $byRole = $this->officesByRole();

        return [
            'contractVersion' => self::SHAPE_VERSION,
            'corpusVersion' => $this->provenance->corpusVersion(),
            'engineVersion' => $this->provenance->engineVersion(),
            'rite' => $this->rite(),
            'edition' => $this->provenance->edition(),
            'date' => $this->day->date()->format('Y-m-d'),
            'season' => $this->daySeason(),
            'commemorationLimit' => $this->commemorationLimit(),
            'celebration' => $byRole[CelebrationRole::CELEBRATION],
            'commemoration' => $byRole[CelebrationRole::COMMEMORATION],
            'displaced' => $byRole[CelebrationRole::DISPLACED],
            'tempora' => $byRole[CelebrationRole::TEMPORA],
            'secondVespers' => $this->secondVespers(),
            'firstVespers' => null,
            'resolution' => $this->resolution(),
            'fasting' => $this->fasting(),
            'calendar' => $this->calendar(),
        ];
    }

    /**
     * The day's fast/abstinence obligation under the active penitential discipline (#250),
     * or null on a day that carries none. The resolver stamps it onto the day
     * ({@see LiturgicalDay::fasting()}) by reading the resolved calendar, so it follows the
     * edition automatically. `reason` names the cited rule that applied; `discipline` and
     * `citation` trace it to the governing law.
     *
     * @return array<string, mixed>|null
     */
    private function fasting(): ?array
    {
        $obligation = $this->day->fasting();
        if ($obligation === null) {
            return null;
        }

        return [
            'fast' => $obligation->fast(),
            'abstinence' => $obligation->abstinence()->value(),
            'discipline' => $obligation->disciplineUrn(),
            'reason' => $obligation->reason(),
            'citation' => $obligation->citation(),
        ];
    }

    /**
     * The `calendar` block: the day's calendrical setting. It always carries the
     * `astronomical` sub-block (#242 — the year's cyclic numbers and the day's
     * lunar age, a pure function of the date); when the day was resolved under a
     * particular calendar (#78) it also names that calendar under `particular`.
     * The reserved `lectionary` field joins it later. See output-contract.md.
     *
     * @return array<string, mixed>
     */
    private function calendar(): array
    {
        $block = [];
        if ($this->calendar !== null) {
            $block['particular'] = $this->calendar->toArray();
        }
        $block['astronomical'] = $this->astronomical();

        return $block;
    }

    /**
     * The calendrical/astronomical block (#242/#245): the year's Golden Number,
     * Epact, Solar Cycle, Dominical Letter(s) and Roman Indiction, with the day's
     * ecclesiastical lunar age — the figures printed at the head of an ordo or in
     * the martyrology. Edition-invariant, derived from the date alone.
     *
     * @return array<string, int|string>
     */
    private function astronomical(): array
    {
        $date = $this->day->date();
        $calendrical = CalendricalYear::forYear((int) $date->format('Y'));

        return [
            'goldenNumber' => $calendrical->goldenNumber(),
            'epact' => $calendrical->epact(),
            'solarCycle' => $calendrical->solarCycle(),
            'dominicalLetter' => $calendrical->dominicalLetter(),
            'romanIndiction' => $calendrical->romanIndiction(),
            'lunarAge' => LunarAge::onDate($date)->age(),
        ];
    }

    /**
     * The show-your-work resolution trace (#233), or null unless the day was resolved
     * with explaining on. The default contract keeps this null, so the frozen shape
     * and the golden digest are unmoved; `explain()` opts in.
     *
     * @return array<string, mixed>|null
     */
    private function resolution(): ?array
    {
        $trace = $this->day->trace();

        return $trace !== null ? $trace->toArray() : null;
    }

    /**
     * The four office roles, each a list of serialised offices in resolver order.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function officesByRole(): array
    {
        $byRole = [
            CelebrationRole::CELEBRATION => [],
            CelebrationRole::COMMEMORATION => [],
            CelebrationRole::DISPLACED => [],
            CelebrationRole::TEMPORA => [],
        ];
        foreach ($this->day->offices() as $office) {
            $byRole[$office->role()->value()][] = $this->officeToArray($office);
        }

        return $byRole;
    }

    /**
     * @return array<string, mixed>
     */
    private function officeToArray(RoledObservance $office): array
    {
        $observance = $office->observance();
        $id = $observance->id()->toString();
        $colour = $observance->colour();

        $shape = [
            'id' => $id,
            'urn' => 'directorium:observance:' . $id,
            'role' => $office->role()->value(),
            'kind' => $observance->kind()->value(),
            'rank' => $observance->rank()->label(),
            'rankOrdinal' => $observance->rank()->ordinal(),
            'season' => null,
            'colour' => [
                'base' => $colour->base()->value(),
                'roseAllowed' => $colour->roseAllowed(),
            ],
            'names' => ['la' => $observance->latinName()],
            'titulars' => [],
            'outcome' => $office->outcome() !== null ? $office->outcome()->value() : null,
            'transferredTo' => self::formatDate($office->transferredTo()),
            'transferredFrom' => self::formatDate($office->transferredFrom()),
            'vigilOf' => null,
            'octaveOf' => null,
            'aliases' => null,
            'citations' => null,
            'text' => null,
            'chant' => null,
            'audio' => null,
        ];

        if ($observance instanceof TemporalObservance) {
            $shape['season'] = $observance->season()->value();

            return $shape;
        }

        if ($observance instanceof SanctoralObservance) {
            $identity = $observance->identity();
            $shape['names'] = $identity->names();
            $shape['titulars'] = $identity->titulars();
            $shape['vigilOf'] = $observance->vigilOfId() !== null
                ? $observance->vigilOfId()->toString()
                : null;
            $shape['octaveOf'] = $observance->octaveOfId() !== null
                ? $observance->octaveOfId()->toString()
                : null;

            return $shape;
        }

        throw new LogicException(sprintf(
            'Cannot serialise unknown realized observance type "%s".',
            get_class($observance)
        ));
    }

    /** The rite segment of the edition id (`roman:rubricae-1960` -> `roman`). */
    private function rite(): string
    {
        return explode(':', $this->provenance->edition())[0];
    }

    /** The day's season, taken from its temporal office, or null on a placeholder. */
    private function daySeason(): ?string
    {
        foreach ($this->day->tempora() as $office) {
            if ($office instanceof TemporalObservance) {
                return $office->season()->value();
            }
        }

        return null;
    }

    /**
     * The commemorations the day admits by its class under the resolving edition (#332), or 0
     * when nothing is celebrated. The resolver stamps the per-edition class cap onto the day
     * ({@see LiturgicalDay::commemorationLimit()}); a day built without one (a synthetic
     * fixture) reports 0.
     */
    private function commemorationLimit(): int
    {
        if ($this->day->celebration() === []) {
            return 0;
        }

        return $this->day->commemorationLimit() ?? 0;
    }

    /**
     * The evening concurrence as a small object, or null when unresolved. The
     * `holder`/`commemorated` slots are reserved for the Office layer.
     *
     * @return array<string, mixed>|null
     */
    private function secondVespers(): ?array
    {
        $outcome = $this->day->secondVespers();
        if (!$outcome instanceof ConcurrenceOutcome) {
            return null;
        }

        return [
            'outcome' => $outcome->value(),
            'favoursFollowing' => $outcome->favoursFollowing(),
            'holder' => null,
            'commemorated' => null,
        ];
    }

    private static function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date !== null ? $date->format('Y-m-d') : null;
    }
}
