<?php

declare(strict_types=1);

namespace Directorium\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Calendar\CelebrationRole;
use Directorium\Core\Calendar\LiturgicalDay;
use Directorium\Core\Calendar\RealizedObservance;
use Directorium\Core\Calendar\RoledObservance;
use Directorium\Core\Contract\Provenance;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Discipline\FastingResolver;
use Directorium\Core\Discipline\PenitentialDiscipline;
use Directorium\Core\Edition\RubricSystem;
use Directorium\Core\Directorium;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Sanctoral\CorpusSanctoralData;
use Directorium\Core\Sanctoral\SanctoralCalendar;
use Directorium\Core\Sanctoral\SanctoralData;
use Directorium\Core\Temporal\MovableFeasts;
use Directorium\Core\Temporal\NovusOrdoTemporalCycle;
use Directorium\Core\Temporal\SaturdayOfOurLady;
use Directorium\Core\Temporal\TemporalCalendar;
use Directorium\Core\Temporal\TemporalCycle;
use Directorium\Core\Temporal\TemporalObservance;
use Directorium\Core\Temporal\TraditionalTemporalCycle;
use Directorium\Core\Trace\ResolutionTrace;

/**
 * The resolver: it composes the temporal skeleton and the sanctoral overlay into
 * one celebrated office per day and assembles the {@see LiturgicalDay}s that back
 * {@see \Directorium\Core\day()}.
 *
 * Because a transferred feast lands on a later free day, the resolution of any
 * one day depends on what was displaced from earlier days, so a whole civil year
 * is resolved in a single deterministic forward sweep: for each day it gathers
 * the temporal office, the movable feasts, and the sanctoral offices (plus any
 * feast transferred onto the day), orders them by the edition's precedence tier,
 * takes the top as the celebration, and resolves every other office to a
 * commemoration, a transfer, or an omission. A second pass fills in the evening
 * concurrence from the following day. See docs/design/precedence-model.md.
 *
 * @internal Not part of the public API (docs/api-stability.md); reach resolution through
 *           {@see \Directorium\Core\day()} / {@see \Directorium\Core\contract()}.
 */
final class DayResolver
{
    private PrecedenceRules $rules;

    private TemporalCycle $temporalCycle;

    private CommemorationSelector $commemorations;

    private string $edition;

    private SanctoralData $sanctoralData;

    private FastingResolver $fasting;

    /** The corpus the temporal fillers read their edition-specific archetypes from. */
    private Corpus $corpus;

    /** The corpus dir key of this resolver's edition, threaded into the temporal fillers. */
    private string $editionDir;

    private bool $tracing;

    private function __construct(
        PrecedenceRules $rules,
        TemporalCycle $temporalCycle,
        string $edition,
        SanctoralData $sanctoralData,
        FastingResolver $fasting,
        Corpus $corpus,
        string $editionDir,
        bool $tracing = false
    ) {
        $this->rules = $rules;
        $this->temporalCycle = $temporalCycle;
        $this->commemorations = new CommemorationSelector($rules);
        $this->edition = $edition;
        $this->sanctoralData = $sanctoralData;
        $this->fasting = $fasting;
        $this->corpus = $corpus;
        $this->editionDir = $editionDir;
        $this->tracing = $tracing;
    }

    /**
     * The resolver for a rubric system (edition): its precedence rules, its edition URN
     * stamped into the contract, and — unless a sanctoral source is supplied (e.g. one
     * wrapped in a particular-calendar overlay) — its own edition data.
     *
     * 1962, 1954 (Divino Afflatu), and 1955 (Cum nostra) resolve today; a system with no
     * engine throws. The default system reproduces {@see for1962()} exactly.
     */
    public static function forEdition(
        RubricSystem $system,
        ?SanctoralData $sanctoralData = null,
        ?Corpus $corpus = null
    ): self {
        $corpus = $corpus ?? Corpus::default();

        return new self(
            self::rulesFor($system, $corpus),
            self::temporalCycleFor($system),
            $system->urn(),
            $sanctoralData ?? new CorpusSanctoralData($corpus, $system->corpusDir()),
            new FastingResolver(PenitentialDiscipline::fromCorpus($corpus, $system->penitentialDiscipline())),
            $corpus,
            $system->corpusDir()
        );
    }

    /**
     * The precedence rules for a rubric system, reading the system's own edition tables.
     */
    private static function rulesFor(RubricSystem $system, Corpus $corpus): PrecedenceRules
    {
        $table = new PrecedenceTable($corpus, $system->corpusDir());
        switch ($system->urn()) {
            case RubricSystem::RUBRICAE_1960:
                return new Rubrics1962Precedence($table);
            case RubricSystem::DIVINO_AFFLATU:
                return new Rubrics1954Precedence($table);
            case RubricSystem::RUBRICAE_1955:
                return new Rubrics1955Precedence($table);
            case RubricSystem::NOVUS_ORDO_2002:
                return new NovusOrdoPrecedence($table);
        }

        throw new \RuntimeException(sprintf(
            'The %s rubric system is declared on the edition axis but its engine is not yet built.',
            $system->label()
        ));
    }

    /**
     * The temporal-cycle strategy for a rubric system: the traditional Proper of Time
     * for the 1962/1954/1955 editions, the Novus-Ordo cycle for the Ordinary-Form
     * snapshots. Keyed off the edition exactly as {@see rulesFor()} is, so the resolver
     * is temporal-cycle-agnostic (docs/design/novus-ordo-calendar-model.md).
     */
    private static function temporalCycleFor(RubricSystem $system): TemporalCycle
    {
        switch ($system->urn()) {
            case RubricSystem::NOVUS_ORDO_2002:
            case RubricSystem::NOVUS_ORDO_1975:
            case RubricSystem::NOVUS_ORDO_1969:
                return new NovusOrdoTemporalCycle();
        }

        return new TraditionalTemporalCycle();
    }

    /** The 1962 resolver (Rubricae 1960): a convenience for {@see forEdition()} with the default system. */
    public static function for1962(?SanctoralData $sanctoralData = null): self
    {
        return self::forEdition(RubricSystem::rubricae1960(), $sanctoralData);
    }

    /**
     * A copy of this resolver that records a {@see ResolutionTrace} on each resolved
     * day (#233). Kept a distinct instance so the default resolution — and the golden
     * digest that hashes it — is never perturbed; explaining a day opts in here.
     */
    public function explaining(): self
    {
        return new self(
            $this->rules,
            $this->temporalCycle,
            $this->edition,
            $this->sanctoralData,
            $this->fasting,
            $this->corpus,
            $this->editionDir,
            true
        );
    }

    /** The edition, corpus, and engine versions this resolver stamps onto a year. */
    public function provenance(): Provenance
    {
        return new Provenance($this->edition, $this->sanctoralData->version(), Directorium::VERSION);
    }

    public function resolveDay(DateTimeImmutable $date): LiturgicalDay
    {
        return $this->resolveYear((int) $date->format('Y'))->day($date);
    }

    public function resolveYear(int $year): ResolvedYear
    {
        $cycle = $this->temporalCycle->forYear($year, $this->corpus, $this->editionDir);
        $movable = MovableFeasts::forYear($year, $this->corpus, $this->editionDir);
        $saturdayOfOurLady = SaturdayOfOurLady::forYear($year, $this->corpus, $this->editionDir);
        $sanctoral = SanctoralCalendar::forYear(
            $year,
            $this->sanctoralData,
            $this->rules->anticipatesSundayVigils()
        );

        $ledger = new TransferLedger();
        /** @var array<string, list<RealizedObservance>> $forced Feasts placed on a fixed target date. */
        $forced = [];
        /** @var array<string, LiturgicalDay> $days */
        $days = [];

        $date = TemporalCalendar::utcDate($year, 1, 1);
        $end = TemporalCalendar::utcDate($year, 12, 31);
        $oneDay = new DateInterval('P1D');

        while ($date <= $end) {
            $key = $date->format('Y-m-d');
            $context = PrecedenceContext::of($date, $cycle->isTriduum($date));

            $temporalOffice = $cycle->office($date);
            $candidates = $this->gather(
                $temporalOffice,
                $movable,
                $saturdayOfOurLady,
                $sanctoral,
                $forced[$key] ?? [],
                $date
            );
            $candidates = $this->sortByTier($candidates, $context);

            $candidates = $this->admitTransferClaimant($candidates, $ledger, $context);
            $candidates = $this->applyOfficeOfTheDeadSundayYield($candidates);

            $days[$key] = $this->assemble($date, $candidates, $temporalOffice, $context, $ledger, $forced);

            $date = $date->add($oneDay);
        }

        $days = $this->withTransferLinks($days);

        return new ResolvedYear($year, $this->withConcurrence($days, $oneDay), $this->provenance());
    }

    /**
     * @param list<RealizedObservance> $forcedToday
     *
     * @return list<RealizedObservance>
     */
    private function gather(
        ?TemporalObservance $temporalOffice,
        MovableFeasts $movable,
        SaturdayOfOurLady $saturdayOfOurLady,
        SanctoralCalendar $sanctoral,
        array $forcedToday,
        DateTimeImmutable $date
    ): array {
        $candidates = [];
        if ($temporalOffice !== null) {
            $candidates[] = $temporalOffice;
        }
        $movableFeast = $movable->on($date);
        if ($movableFeast !== null) {
            $candidates[] = $movableFeast;
        }
        // The votive Office of Our Lady on a free Saturday (#453): an edition-gated overlay
        // (1954/1955 only), it competes as an ordinary candidate — winning a free Saturday
        // over a Simple / feria, yielding (dropped) to any Semidouble-or-higher.
        $ladyOffice = $saturdayOfOurLady->on($date);
        if ($ladyOffice !== null) {
            $candidates[] = $ladyOffice;
        }
        foreach ($sanctoral->on($date) as $office) {
            $candidates[] = $office;
        }
        foreach ($forcedToday as $office) {
            $candidates[] = $office;
        }

        return $candidates;
    }

    /**
     * If the day's natural celebration is a free (third- or fourth-class) day and
     * a first-class feast is waiting in the ledger, that feast claims the day.
     *
     * @param list<RealizedObservance> $candidates
     *
     * @return list<RealizedObservance>
     */
    private function admitTransferClaimant(array $candidates, TransferLedger $ledger, PrecedenceContext $context): array
    {
        // A pending transfer claims only a genuinely free day. The Office of the Dead (All
        // Souls) is never displaced by a queued feast — even where it normalises to a third-
        // class grade under the pre-1955 editions — so it is never treated as free.
        if (
            $candidates === []
            || $ledger->isEmpty()
            || $candidates[0]->rank()->ordinal() < 3
            || $candidates[0]->kind()->value() === ObservanceKind::OFFICE_OF_THE_DEAD
        ) {
            return $candidates;
        }

        $candidates[] = $ledger->dequeue();

        return $this->sortByTier($candidates, $context);
    }

    /**
     * The Office of the Dead (All Souls) is never celebrated on a Sunday — a requiem is not
     * sung on the day of the Resurrection — so although it outranks the Sunday in the Table of
     * Liturgical Days, it yields the day to it and is kept on the next free day (the rubric of
     * 2 November; n. 96b). Editions that keep this rule answer
     * {@see PrecedenceRules::officeOfTheDeadYieldsToSunday()} true; the reformed calendar, which
     * celebrates All Souls even on a Sunday, answers false and this is a no-op.
     *
     * Promoting the Sunday makes it the celebration and drops All Souls into the loser pool,
     * where {@see PrecedenceRules::occurrenceOutcome()} transfers it — the only transferable
     * loser — to the next free day through the ordinary transfer ledger (typically 3 November).
     *
     * @param list<RealizedObservance> $candidates already ordered by tier
     *
     * @return list<RealizedObservance>
     */
    private function applyOfficeOfTheDeadSundayYield(array $candidates): array
    {
        if (
            $candidates === []
            || !$this->rules->officeOfTheDeadYieldsToSunday()
            || $candidates[0]->kind()->value() !== ObservanceKind::OFFICE_OF_THE_DEAD
        ) {
            return $candidates;
        }

        foreach ($candidates as $index => $candidate) {
            if ($index === 0 || $candidate->kind()->value() !== ObservanceKind::SUNDAY) {
                continue;
            }
            array_splice($candidates, $index, 1);
            array_unshift($candidates, $candidate);

            return $candidates;
        }

        return $candidates;
    }

    /**
     * @param list<RealizedObservance>              $candidates
     * @param array<string, list<RealizedObservance>> $forced
     */
    private function assemble(
        DateTimeImmutable $date,
        array $candidates,
        ?TemporalObservance $temporalOffice,
        PrecedenceContext $context,
        TransferLedger $ledger,
        array &$forced
    ): LiturgicalDay {
        if ($candidates === []) {
            return LiturgicalDay::placeholder($date);
        }

        $celebration = $candidates[0];
        $commemorationCandidates = [];

        /** @var list<RoledObservance> $displaced */
        $displaced = [];

        /** @var list<array{id: string, outcome: string, reason: \Directorium\Core\Trace\ResolutionReason}> $traceLosers */
        $traceLosers = [];

        foreach (array_slice($candidates, 1) as $loser) {
            $outcome = $this->rules->occurrenceOutcome($celebration, $loser, $context);
            if ($this->tracing) {
                $traceLosers[] = [
                    'id' => $loser->id()->toString(),
                    'outcome' => $outcome->value(),
                    'reason' => $this->rules->explainOccurrence($celebration, $loser, $context),
                ];
            }
            if ($outcome->isTransfer()) {
                $this->scheduleTransfer($loser, $date, $context, $ledger, $forced);
                $displaced[] = new RoledObservance($loser, CelebrationRole::displaced(), $outcome);
            } elseif ($outcome->isCommemoration()) {
                $commemorationCandidates[] = $loser;
            } else {
                $displaced[] = new RoledObservance($loser, CelebrationRole::displaced(), $outcome);
            }
        }

        $selected = $this->commemorations->select($celebration, $commemorationCandidates, $context);

        /** @var list<RoledObservance> $commemorations */
        $commemorations = [];
        foreach ($selected as $office) {
            $commemorations[] = new RoledObservance(
                $office,
                CelebrationRole::commemoration(),
                OccurrenceOutcome::commemorate()
            );
        }

        // A commemoration eligible on the merits but trimmed by the day's limit
        // (n. 114) is dropped from the day: displaced, marked omitted.
        foreach ($commemorationCandidates as $candidate) {
            if (!$this->contains($selected, $candidate)) {
                $displaced[] = new RoledObservance($candidate, CelebrationRole::displaced(), OccurrenceOutcome::omit());
            }
        }

        $tempora = $temporalOffice !== null
            ? [new RoledObservance($temporalOffice, CelebrationRole::tempora())]
            : [];

        // The penitential obligation reads the resolved day's own properties (its weekday,
        // season, and the offices' kinds/ids), so a vigil or Ember day the edition suppressed
        // never produces one — per-edition correctness without a per-edition discipline. Every
        // office in play is scanned — including the displaced — because the fast attaches to
        // the day, not to whichever office won: a fasting vigil outranked to omission still
        // carries its fast.
        $displacedObservances = [];
        foreach ($displaced as $roled) {
            $displacedObservances[] = $roled->observance();
        }
        $fasting = $this->fasting->resolve(
            $date,
            $temporalOffice !== null ? $temporalOffice->season()->value() : null,
            array_merge(
                [$celebration],
                $temporalOffice !== null ? [$temporalOffice] : [],
                $selected,
                $displacedObservances
            )
        );

        $day = new LiturgicalDay(
            $date,
            [new RoledObservance($celebration, CelebrationRole::celebration())],
            $commemorations,
            $displaced,
            $tempora,
            null,
            null,
            $this->rules->commemorationClassLimit($celebration),
            $fasting
        );

        if (!$this->tracing) {
            return $day;
        }

        return $day->withTrace($this->buildTrace($candidates, $celebration, $traceLosers, $temporalOffice, $context));
    }

    /**
     * The show-your-work trace of one day's resolution (#233): the sorted field of
     * candidates with their tiers, the celebrated winner and why it won, each loser's
     * occurrence outcome and cited reason, the day's commemoration limit, and how its
     * colour and season were derived (#235).
     *
     * @param list<RealizedObservance> $candidates
     * @param list<array{id: string, outcome: string, reason: \Directorium\Core\Trace\ResolutionReason}> $losers
     */
    private function buildTrace(
        array $candidates,
        RealizedObservance $celebration,
        array $losers,
        ?TemporalObservance $temporalOffice,
        PrecedenceContext $context
    ): ResolutionTrace {
        $candidateRows = [];
        foreach ($candidates as $candidate) {
            $tier = $this->rules->tierOf($candidate, $context);
            $candidateRows[] = [
                'id' => $candidate->id()->toString(),
                'rank' => $candidate->rank()->ordinal(),
                'kind' => $candidate->kind()->value(),
                'tier' => [
                    'ordinal' => $tier->ordinal(),
                    'line' => $tier->line(),
                    'selector' => $tier->selector(),
                ],
            ];
        }

        $colour = $celebration->colour();
        $season = $temporalOffice !== null ? $temporalOffice->season()->value() : null;

        return new ResolutionTrace(
            [
                'id' => $celebration->id()->toString(),
                'line' => $this->rules->tierOf($celebration, $context)->line(),
                'reason' => $this->rules->explainPrecedence($celebration, $context),
            ],
            $candidateRows,
            $losers,
            $this->rules->commemorationLimit($celebration, $context),
            $this->rules->explainCommemorationLimit($celebration, $context),
            [
                'base' => $colour->base()->value(),
                'roseAllowed' => $colour->roseAllowed(),
                'reason' => $this->rules->explainColour($celebration),
            ],
            [
                'value' => $season,
                'reason' => $this->rules->explainSeason($season),
            ]
        );
    }

    /**
     * @param array<string, list<RealizedObservance>> $forced
     */
    private function scheduleTransfer(
        RealizedObservance $feast,
        DateTimeImmutable $impededOn,
        PrecedenceContext $context,
        TransferLedger $ledger,
        array &$forced
    ): void {
        $target = $this->rules->forcedTransferDate($feast, $context);
        if ($target !== null) {
            $forced[$target->format('Y-m-d')][] = $feast;

            return;
        }

        $ledger->enqueue($feast, $impededOn);
    }

    /**
     * @param array<string, LiturgicalDay> $days
     *
     * @return array<string, LiturgicalDay>
     */
    private function withConcurrence(array $days, DateInterval $oneDay): array
    {
        // An edition whose Office has no evening concurrence contest (the Novus Ordo) skips the
        // pass entirely rather than stamping a meaningless second-Vespers outcome on every day.
        if (!$this->rules->observesVespersConcurrence()) {
            return $days;
        }

        foreach ($days as $key => $day) {
            $nextKey = $day->date()->add($oneDay)->format('Y-m-d');
            if (!isset($days[$nextKey]) || $day->celebration() === [] || $days[$nextKey]->celebration() === []) {
                continue;
            }

            $outcome = $this->rules->concurrenceOutcome(
                $day->celebration()[0],
                $days[$nextKey]->celebration()[0],
                PrecedenceContext::of($day->date(), false)
            );

            $days[$key] = $day->withSecondVespers($outcome);
        }

        return $days;
    }

    /**
     * Reconciliation pass: with the whole year resolved, link the ends of every
     * transfer so each office is self-describing — `transferredTo` on the day a
     * feast was impeded, `transferredFrom` on the day it lands. The output
     * contract (#52) then needs no cross-day correlation.
     *
     * A feast can be impeded more than once — a first-class feast transferred to
     * a free day that is itself later claimed by a higher pending feast, bumping
     * it onward — so links are computed from the *chain* of a feast's
     * appearances across the year, not a single impeded/landing pair: each hop
     * points to the next, and only a celebration reached from an earlier
     * impediment is stamped `transferredFrom`. That also keeps a feast celebrated
     * on its own date from being mistaken for a landing.
     *
     * @param array<string, LiturgicalDay> $days
     *
     * @return array<string, LiturgicalDay>
     */
    private function withTransferLinks(array $days): array
    {
        $links = $this->transferLinks($days);
        if ($links === []) {
            return $days;
        }

        foreach ($days as $key => $day) {
            if (!isset($links[$key])) {
                continue;
            }
            $rewritten = [];
            $changed = false;
            foreach ($day->offices() as $office) {
                $link = $links[$key][$office->observance()->id()->toString()] ?? [];
                $to = $link['to'] ?? null;
                $from = $link['from'] ?? null;
                if ($to !== null || $from !== null) {
                    $rewritten[] = $office->withTransfer($to, $from);
                    $changed = true;
                } else {
                    $rewritten[] = $office;
                }
            }
            if ($changed) {
                $days[$key] = $day->withOffices($rewritten);
            }
        }

        return $days;
    }

    /**
     * The transfer links to stamp, indexed by date key then feast id.
     *
     * For each feast impeded at least once, its appearances (each a
     * displaced-transfer "moved on" node or a celebration node) are ordered by
     * date and linked hop to hop: a moved node points `to` the next appearance,
     * and a celebration reached from a moved node points `from` it.
     *
     * @param array<string, LiturgicalDay> $days
     *
     * @return array<string, array<string, array<string, DateTimeImmutable>>>
     */
    private function transferLinks(array $days): array
    {
        /** @var array<string, list<array{date: DateTimeImmutable, moved: bool}>> $appearances */
        $appearances = [];
        foreach ($days as $day) {
            foreach ($day->offices() as $office) {
                $role = $office->role()->value();
                $moved = $role === CelebrationRole::DISPLACED
                    && $office->outcome() !== null
                    && $office->outcome()->isTransfer();
                if (!$moved && $role !== CelebrationRole::CELEBRATION) {
                    continue;
                }
                $appearances[$office->observance()->id()->toString()][] = [
                    'date' => $day->date(),
                    'moved' => $moved,
                ];
            }
        }

        /** @var array<string, array<string, array<string, DateTimeImmutable>>> $links */
        $links = [];
        foreach ($appearances as $id => $sequence) {
            if (!self::wasImpeded($sequence)) {
                continue;
            }
            usort(
                $sequence,
                static fn (array $a, array $b): int => $a['date']->getTimestamp() <=> $b['date']->getTimestamp()
            );

            $last = count($sequence) - 1;
            for ($i = 0; $i < $last; $i++) {
                if (!$sequence[$i]['moved']) {
                    continue;
                }
                $next = $sequence[$i + 1];
                $links[$sequence[$i]['date']->format('Y-m-d')][$id]['to'] = $next['date'];
                if (!$next['moved']) {
                    $links[$next['date']->format('Y-m-d')][$id]['from'] = $sequence[$i]['date'];
                }
            }
        }

        return $links;
    }

    /**
     * @param list<array{date: DateTimeImmutable, moved: bool}> $sequence
     */
    private static function wasImpeded(array $sequence): bool
    {
        foreach ($sequence as $node) {
            if ($node['moved']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<RealizedObservance> $candidates
     *
     * @return list<RealizedObservance>
     */
    private function sortByTier(array $candidates, PrecedenceContext $context): array
    {
        usort(
            $candidates,
            function (RealizedObservance $a, RealizedObservance $b) use ($context): int {
                $byTier = $this->rules->tierOf($a, $context)->compareTo($this->rules->tierOf($b, $context));

                return $byTier !== 0 ? $byTier : $a->id()->toString() <=> $b->id()->toString();
            }
        );

        return $candidates;
    }

    /**
     * @param list<RealizedObservance> $haystack
     */
    private function contains(array $haystack, RealizedObservance $needle): bool
    {
        foreach ($haystack as $office) {
            if ($office->id()->equals($needle->id())) {
                return true;
            }
        }

        return false;
    }
}
