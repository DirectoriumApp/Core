<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateInterval;
use DateTimeImmutable;
use Introibo\Core\Calendar\LiturgicalDay;
use Introibo\Core\Calendar\RealizedObservance;
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

    private ?SanctoralData $sanctoralData;

    private function __construct(PrecedenceRules $rules, ?SanctoralData $sanctoralData)
    {
        $this->rules = $rules;
        $this->commemorations = new CommemorationSelector($rules);
        $this->sanctoralData = $sanctoralData;
    }

    public static function for1962(?SanctoralData $sanctoralData = null): self
    {
        return new self(new Rubrics1962Precedence(), $sanctoralData);
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

        return new ResolvedYear($year, $this->withConcurrence($days, $oneDay));
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
        $displaced = [];

        foreach (array_slice($candidates, 1) as $loser) {
            $outcome = $this->rules->occurrenceOutcome($celebration, $loser, $context);
            if ($outcome->isTransfer()) {
                $this->scheduleTransfer($loser, $date, $context, $ledger, $forced);
                $displaced[] = $loser;
            } elseif ($outcome->isCommemoration()) {
                $commemorationCandidates[] = $loser;
            } else {
                $displaced[] = $loser;
            }
        }

        $commemorations = $this->commemorations->select($celebration, $commemorationCandidates, $context);
        foreach ($commemorationCandidates as $candidate) {
            if (!$this->contains($commemorations, $candidate)) {
                $displaced[] = $candidate;
            }
        }

        $tempora = $temporalOffice !== null ? [$temporalOffice] : [];

        return new LiturgicalDay($date, [$celebration], $commemorations, $displaced, $tempora);
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

            $days[$key] = new LiturgicalDay(
                $day->date(),
                $day->celebration(),
                $day->commemoration(),
                $day->displaced(),
                $day->tempora(),
                $outcome
            );
        }

        return $days;
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
