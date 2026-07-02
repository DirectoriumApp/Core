<?php

declare(strict_types=1);

namespace Introibo\Core\Contract;

use DateTimeImmutable;
use Introibo\Core\Calendar\CelebrationRole;
use Introibo\Core\Calendar\CommemorationLimit;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Calendar\RoledObservance;
use Introibo\Core\Precedence\ConcurrenceOutcome;
use Introibo\Core\Sanctoral\SanctoralObservance;
use Introibo\Core\Temporal\TemporalObservance;
use LogicException;

/**
 * The versioned, serialisable shape of a resolved {@see LiturgicalDay} — the
 * public output contract every other Introibo repo (Api/Site/Ordo) builds on.
 *
 * {@see LiturgicalDay} is kept a pure aggregate; this class is the seam that
 * turns it into a stable JSON-ready structure. The shape is frozen at
 * {@see SHAPE_VERSION} 1.0.0: a day carries its three provenance axes
 * ({@see Provenance}) and the four office roles, each office a self-describing
 * record of identity, per-edition attributes, occurrence outcome, and transfer
 * links. Reserved slots (`firstVespers`, `resolution`, `fasting`, `calendar`,
 * and the office-level `octaveOf`/`aliases`/`citations`/`text`/`chant`/`audio`)
 * are emitted as null now and only ever filled later, so the contract grows
 * additively. Serialisation is deterministic: same inputs, byte-identical JSON.
 * See docs/design/output-contract.md.
 */
final class DayContract
{
    /** SemVer of the contract *shape* (distinct from the corpus and engine versions). */
    public const SHAPE_VERSION = '1.0.0';

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
            'fasting' => null,
            'calendar' => $this->calendar(),
        ];
    }

    /**
     * The `calendar` block. Under the universal 1962 calendar it stays null (the
     * frozen default shape); when the day was resolved under a particular calendar
     * (#78) it names that calendar under a `particular` key — additive, leaving room
     * for the reserved astronomical/lectionary fields (v0.4). See output-contract.md.
     *
     * @return array<string, mixed>|null
     */
    private function calendar(): ?array
    {
        if ($this->calendar === null) {
            return null;
        }

        return ['particular' => $this->calendar->toArray()];
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
            'urn' => 'introibo:observance:' . $id,
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

    /** The commemorations the day admits by its class, or 0 when nothing is celebrated. */
    private function commemorationLimit(): int
    {
        $celebration = $this->day->celebration();
        if ($celebration === []) {
            return 0;
        }

        return CommemorationLimit::forDayClass($celebration[0]->rank());
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
