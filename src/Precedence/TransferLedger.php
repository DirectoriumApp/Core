<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateTimeImmutable;
use Introibo\Core\Calendar\RealizedObservance;
use LogicException;

/**
 * The queue of first-class feasts awaiting transfer to a free day.
 *
 * When occurrence transfers a first-class feast (n. 95), the resolver's year
 * sweep (#37) enqueues it here with the day it was impeded on, then drains the
 * queue as free days come up — placing the highest-precedence pending feast
 * first. A feast with a rubrically fixed target (the Annunciation, n. 96a) is
 * placed on that date directly and does not pass through this queue.
 *
 * Competing claimants are ordered deterministically: the earliest-impeded first,
 * then by canonical id (all transferred feasts are first class, so a finer
 * class sub-order does not arise in the current data).
 */
final class TransferLedger
{
    /** @var list<array{feast: RealizedObservance, impededOn: DateTimeImmutable}> */
    private array $pending = [];

    public function enqueue(RealizedObservance $feast, DateTimeImmutable $impededOn): void
    {
        $this->pending[] = ['feast' => $feast, 'impededOn' => $impededOn];
    }

    public function isEmpty(): bool
    {
        return $this->pending === [];
    }

    public function count(): int
    {
        return count($this->pending);
    }

    /**
     * Remove and return the highest-precedence pending feast: the earliest
     * impeded, then by canonical id.
     */
    public function dequeue(): RealizedObservance
    {
        if ($this->pending === []) {
            throw new LogicException('Cannot dequeue from an empty transfer ledger.');
        }

        $best = 0;
        $count = count($this->pending);
        for ($i = 1; $i < $count; $i++) {
            if ($this->outranks($this->pending[$i], $this->pending[$best])) {
                $best = $i;
            }
        }

        $feast = $this->pending[$best]['feast'];
        array_splice($this->pending, $best, 1);
        $this->pending = array_values($this->pending);

        return $feast;
    }

    /**
     * @param array{feast: RealizedObservance, impededOn: DateTimeImmutable} $candidate
     * @param array{feast: RealizedObservance, impededOn: DateTimeImmutable} $incumbent
     */
    private function outranks(array $candidate, array $incumbent): bool
    {
        $byDate = $candidate['impededOn']->getTimestamp() <=> $incumbent['impededOn']->getTimestamp();
        if ($byDate !== 0) {
            return $byDate < 0;
        }

        return $candidate['feast']->id()->toString() < $incumbent['feast']->id()->toString();
    }
}
