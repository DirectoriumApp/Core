<?php

declare(strict_types=1);

namespace Introibo\Core\Precedence;

use Introibo\Core\Calendar\RealizedObservance;

/**
 * The precedence rules of one rubric edition.
 *
 * This is the per-edition seam (the abstraction #59 will use to add the 1954,
 * 1955, and Novus Ordo editions): the resolver pipeline is edition-agnostic and
 * asks the rules object every question whose answer is edition-specific. The only
 * implementation now is {@see Rubrics1962Precedence}.
 *
 * The interface grows as the resolver epic (#29) advances: this scaffold (#30)
 * defines the table lookup; occurrence and transfer outcomes (#31–#34),
 * concurrence (#35), and commemoration limits (#36) are added by their issues.
 */
interface PrecedenceRules
{
    /**
     * The office's line in this edition's Table of Liturgical Days — the sort
     * key occurrence resolves on.
     */
    public function tierOf(RealizedObservance $observance, PrecedenceContext $context): PrecedenceTier;
}
