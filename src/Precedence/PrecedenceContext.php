<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use DateTimeImmutable;

/**
 * The day-level facts a {@see PrecedenceRules} needs that are not carried by an
 * individual office.
 *
 * Precedence depends on a little context beyond the office itself — chiefly
 * whether the day is one of the Sacred Triduum, which lifts its ferias to the
 * apex of the table even though their kind and class do not say so. The resolver
 * builds one context per day (from the paschal skeleton) and passes it to every
 * lookup for that day. More day-level facts (privileged-octave membership, the
 * neighbouring day for concurrence) are added as later issues need them.
 */
final class PrecedenceContext
{
    private DateTimeImmutable $date;

    private bool $triduum;

    private function __construct(DateTimeImmutable $date, bool $triduum)
    {
        $this->date = $date;
        $this->triduum = $triduum;
    }

    public static function of(DateTimeImmutable $date, bool $triduum): self
    {
        return new self($date, $triduum);
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    /** True on Maundy Thursday, Good Friday, and Holy Saturday. */
    public function isTriduum(): bool
    {
        return $this->triduum;
    }
}
