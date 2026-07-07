<?php

declare(strict_types=1);

namespace Directorium\Core\Discipline;

use DateTimeImmutable;
use Directorium\Core\Calendar\RealizedObservance;

/**
 * Derives a day's {@see FastingObligation} from a resolved day and a penitential
 * discipline (#249).
 *
 * The resolver reads the already-resolved day — its weekday, its season, and the
 * offices in play (their kind and id) — computes the penitential conditions that hold
 * (a Friday, a Lenten feria, an Ember day, the vigil of a fasting feast, Ash Wednesday),
 * and looks each up in the discipline's cited rules. Because it reads the day *as the
 * edition resolved it*, per-edition correctness is emergent: a vigil an edition
 * suppressed is not a vigil day here, so no fast attaches — the discipline is never
 * duplicated per edition. Sundays are exempt. Where several rules apply, the day keeps
 * the strictest: fast if any prescribes it, and the strongest abstinence.
 */
final class FastingResolver
{
    /** The temporal id of Ash Wednesday, the one Lenten day that is complete abstinence by name. */
    private const ASH_WEDNESDAY_ID = 'roman:temporale:paschal:ash-wednesday';

    /** The seasons that make up the Lenten fast: Lent proper and Passiontide (through Holy Saturday). */
    private const LENTEN_SEASONS = ['lent', 'passiontide'];

    /** Which rule names the reason/citation when several apply — most characteristic first. */
    private const REASON_PRIORITY = ['ash-wednesday', 'ember-day', 'vigil', 'lent-major', 'lent-minor', 'friday'];

    private PenitentialDiscipline $discipline;

    public function __construct(PenitentialDiscipline $discipline)
    {
        $this->discipline = $discipline;
    }

    /**
     * The obligation for a resolved day, or null when the discipline lays none on it.
     *
     * @param list<RealizedObservance> $offices the offices in play on the day (the celebration,
     *        its temporal office, and any commemorations) — scanned for the ember/vigil/Ash signals
     */
    public function resolve(DateTimeImmutable $date, ?string $season, array $offices): ?FastingObligation
    {
        // 1 = Monday … 7 = Sunday. Sundays are never days of fast or abstinence.
        $weekday = (int) $date->format('N');
        if ($weekday === 7) {
            return null;
        }

        $applied = [];
        foreach ($this->tagsFor($weekday, $season, $offices) as $tag) {
            $rule = $this->discipline->rule($tag);
            if ($rule !== null) {
                $applied[$tag] = $rule;
            }
        }

        return $applied === [] ? null : $this->combine($applied);
    }

    /**
     * The penitential conditions that hold on the day, as rule-name tags.
     *
     * @param list<RealizedObservance> $offices
     *
     * @return list<string>
     */
    private function tagsFor(int $weekday, ?string $season, array $offices): array
    {
        $tags = [];

        // Every Friday is a day of abstinence.
        if ($weekday === 5) {
            $tags[] = 'friday';
        }

        $isAshWednesday = false;
        $isEmberDay = false;
        $isFastingVigil = false;
        foreach ($offices as $office) {
            $id = $office->id()->toString();
            $kind = $office->kind()->value();
            if ($id === self::ASH_WEDNESDAY_ID) {
                $isAshWednesday = true;
            }
            if ($kind === 'ember-day') {
                $isEmberDay = true;
            }
            if ($kind === 'vigil' && $this->discipline->isFastingVigil($id)) {
                $isFastingVigil = true;
            }
        }

        // The Lenten fast: Ash Wednesday, then the weekdays of Lent — Fridays and Saturdays
        // to fast with complete abstinence, the rest to fast alone (no abstinence, c.1252 §3).
        if ($isAshWednesday) {
            $tags[] = 'ash-wednesday';
        } elseif (in_array($season, self::LENTEN_SEASONS, true)) {
            $tags[] = ($weekday === 5 || $weekday === 6) ? 'lent-major' : 'lent-minor';
        }

        if ($isEmberDay) {
            $tags[] = 'ember-day';
        }
        if ($isFastingVigil) {
            $tags[] = 'vigil';
        }

        return $tags;
    }

    /**
     * Fold the applicable rules into one obligation: fast if any prescribes it, the
     * strictest abstinence, and the reason/citation of the most characteristic rule.
     *
     * @param array<string, array{fast: bool, abstinence: Abstinence, cite: string}> $applied
     */
    private function combine(array $applied): FastingObligation
    {
        $fast = false;
        $abstinence = Abstinence::none();
        foreach ($applied as $rule) {
            $fast = $fast || $rule['fast'];
            if ($rule['abstinence']->isStricterThan($abstinence)) {
                $abstinence = $rule['abstinence'];
            }
        }

        foreach (self::REASON_PRIORITY as $tag) {
            if (isset($applied[$tag])) {
                return new FastingObligation(
                    $fast,
                    $abstinence,
                    $tag,
                    $this->discipline->urn(),
                    $applied[$tag]['cite']
                );
            }
        }

        // Unreachable: every emitted tag is in REASON_PRIORITY. Fail safe on the first.
        $tag = (string) array_key_first($applied);

        return new FastingObligation($fast, $abstinence, $tag, $this->discipline->urn(), $applied[$tag]['cite']);
    }
}
