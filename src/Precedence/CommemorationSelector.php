<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use Introibo\Core\Calendar\RealizedObservance;

/**
 * Selects and orders the commemorations admitted on a day.
 *
 * Given the celebrated office and the offices that yielded to it as
 * commemorations, this applies the edition's per-day limit and ordering:
 * privileged commemorations first (so a tight count keeps them), then by
 * precedence tier, then by canonical id — trimmed to the day's admitted count.
 * Commemorations beyond the limit are dropped (n. 114).
 */
final class CommemorationSelector
{
    private PrecedenceRules $rules;

    public function __construct(PrecedenceRules $rules)
    {
        $this->rules = $rules;
    }

    /**
     * @param list<RealizedObservance> $candidates
     *
     * @return list<RealizedObservance>
     */
    public function select(
        RealizedObservance $celebration,
        array $candidates,
        PrecedenceContext $context
    ): array {
        $limit = $this->rules->commemorationLimit($celebration, $context);
        if ($limit === 0 || $candidates === []) {
            return [];
        }

        usort(
            $candidates,
            function (RealizedObservance $a, RealizedObservance $b) use ($context): int {
                return $this->compare($a, $b, $context);
            }
        );

        return array_slice($candidates, 0, $limit);
    }

    private function compare(RealizedObservance $a, RealizedObservance $b, PrecedenceContext $context): int
    {
        // Privileged commemorations first, so a tight limit keeps them (n. 108 / 114).
        $privilegedA = $this->rules->isPrivilegedCommemoration($a) ? 0 : 1;
        $privilegedB = $this->rules->isPrivilegedCommemoration($b) ? 0 : 1;
        if ($privilegedA !== $privilegedB) {
            return $privilegedA <=> $privilegedB;
        }

        $byTier = $this->rules->tierOf($a, $context)->compareTo($this->rules->tierOf($b, $context));
        if ($byTier !== 0) {
            return $byTier;
        }

        return $a->id()->toString() <=> $b->id()->toString();
    }
}
