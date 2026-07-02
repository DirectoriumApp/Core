<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Calendar\CelebrationRole;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Calendar\RealizedObservance;
use Introibo\Core\Calendar\RoledObservance;
use Introibo\Core\Contract\Provenance;
use Introibo\Core\Introibo;
use Introibo\Core\Sanctoral\CorpusSanctoralData;
use Introibo\Core\Sanctoral\SanctoralCalendar;
use Introibo\Core\Sanctoral\SanctoralData;
use Introibo\Core\Temporal\ChristmasCycle;
use Introibo\Core\Temporal\Eastertide;
use Introibo\Core\Temporal\HolyWeek;
use Introibo\Core\Temporal\LentenCycle;
use Introibo\Core\Temporal\MovableFeasts;
use Introibo\Core\Temporal\TemporalCalendar;
use Introibo\Core\Temporal\TemporalObservance;
use Introibo\Core\Temporal\TimeAfterPentecost;

/**
 * The resolver: it composes the temporal skeleton and the sanctoral overlay into
 * one celebrated office per day and assembles the {@see LiturgicalDay}s that back
 * {@see \Introibo\Core\day()}.
 *
 * Because a transferred feast lands on a later free day, the resolution of any
 * one day depends on what was displaced from earlier days, so a whole civil year
 * is resolved in a single deterministic forward sweep: for each day it gathers
 * the temporal office, the movable feasts, and the sanctoral offices (plus any
 * feast transferred onto the day), orders them by the edition's precedence tier,
 * takes the top as the celebration, and resolves every other office to a
 * commemoration, a transfer, or an omission. A second pass fills in the evening
 * concurrence from the following day. See docs/design/precedence-model.md.
 */
final class DayResolver
{
    private PrecedenceRules $rules;

    private CommemorationSelector $commemorations;

    private string $edition;

    private SanctoralData $sanctoralData;

    private function __construct(PrecedenceRules $rules, string $edition, SanctoralData $sanctoralData)
    {
        $this->rules = $rules;
        $this->commemorations = new CommemorationSelector($rules);
        $this->edition = $edition;
        $this->sanctoralData = $sanctoralData;
    }

    public static function for1962(?SanctoralData $sanctoralData = null): self
    {
        return new self(
            new Rubrics1962Precedence(),
            'roman:rubricae-1960',
            $sanctoralData ?? new CorpusSanctoralData()
        );
    }

    /** The edition, corpus, and engine versions this resolver stamps onto a year. */
    public function provenance(): Provenance
    {
        return new Provenance($this->edition, $this->sanctoralData->version(), Introibo::VERSION);
    }

    public function resolveDay(DateTimeImmutable $date): LiturgicalDay
    {
        return $this->resolveYear((int) $date->format('Y'))->day($date);
    }

    public function resolveYear(int $year): ResolvedYear
    {
        $holyWeek = HolyWeek::forYear($year);
        $temporal = [
            ChristmasCycle::forYear($year - 1),
            LentenCycle::forYear($year),
            $holyWeek,
            Eastertide::forYear($year),
            TimeAfterPentecost::forYear($year),
            ChristmasCycle::forYear($year),
        ];
        $movable = MovableFeasts::forYear($year);
        $sanctoral = SanctoralCalendar::forYear($year, $this->sanctoralData);

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
            $context = PrecedenceContext::of($date, $holyWeek->isTriduum($date));

            $temporalOffice = $this->temporalOffice($temporal, $date);
            $candidates = $this->gather($temporalOffice, $movable, $sanctoral, $forced[$key] ?? [], $date);
            $candidates = $this->sortByTier($candidates, $context);

            $candidates = $this->admitTransferClaimant($candidates, $ledger, $context);

            $days[$key] = $this->assemble($date, $candidates, $temporalOffice, $context, $ledger, $forced);

            $date = $date->add($oneDay);
        }

        $days = $this->withTransferLinks($days);

        return new ResolvedYear($year, $this->withConcurrence($days, $oneDay), $this->provenance());
    }

    /**
     * @param list<ChristmasCycle|LentenCycle|HolyWeek|Eastertide|TimeAfterPentecost> $fillers
     */
    private function temporalOffice(array $fillers, DateTimeImmutable $date): ?TemporalObservance
    {
        foreach ($fillers as $filler) {
            $office = $filler->on($date);
            if ($office !== null) {
                return $office;
            }
        }

        return null;
    }

    /**
     * @param list<RealizedObservance> $forcedToday
     *
     * @return list<RealizedObservance>
     */
    private function gather(
        ?TemporalObservance $temporalOffice,
        MovableFeasts $movable,
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
        if ($candidates === [] || $ledger->isEmpty() || $candidates[0]->rank()->ordinal() < 3) {
            return $candidates;
        }

        $candidates[] = $ledger->dequeue();

        return $this->sortByTier($candidates, $context);
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

        foreach (array_slice($candidates, 1) as $loser) {
            $outcome = $this->rules->occurrenceOutcome($celebration, $loser, $context);
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

        return new LiturgicalDay(
            $date,
            [new RoledObservance($celebration, CelebrationRole::celebration())],
            $commemorations,
            $displaced,
            $tempora
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
