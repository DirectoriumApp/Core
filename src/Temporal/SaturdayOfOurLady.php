<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateInterval;
use DateTimeImmutable;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Observance\ObservanceId;
use InvalidArgumentException;

/**
 * The votive Office of Our Lady on a free Saturday — the Officium Sanctae Mariae in
 * Sabbato (#453), the whole ferial Saturday Office said OF OUR LADY on a Saturday whose
 * Office would otherwise be at most a Simple or the ordinary (non-privileged) feria. It is
 * NOT the Little Office of the BVM (a separate devotional cursus); it is the Office of the
 * day, white, of the ritus simplex.
 *
 * Like {@see MovableFeasts} this is an edition-gated OVERLAY: it mints a
 * `lady-on-saturday` office only when the edition declares the `sancta-maria-sabbato`
 * archetype — 1954 (Divino Afflatu) and its 1955 (Cum nostra) derivation — and NEVER for
 * 1962, whose {@see TemporalAttributes::has()} is false, so the base calendar (and its
 * golden fixture) is unmoved by construction.
 *
 * It mints on every Saturday of the civil year OUTSIDE the seasons in which the Office is
 * not said: Advent, the Christmas-vigil → Epiphany-octave block (24 Dec – 13 Jan), and
 * Lent & Passiontide (whose ferias are privileged). Pre-Lent (Septuagesima) Saturdays are
 * NOT excluded. The per-day contest with a coincident feast, feria, vigil, or Ember day is
 * left to the precedence engine ({@see \Directorium\Core\Precedence\Rubrics1954Precedence}):
 * a Semidouble-or-higher — and every privileged feria / vigil / Ember day / semidouble-plus
 * octave-day — takes the Saturday and the lady office is DROPPED, not commemorated; a Simple
 * is commemorated under it; the ordinary feria simply yields. See
 * docs/design/rubric-system-model.md (Seam 9).
 */
final class SaturdayOfOurLady
{
    /** The corpus archetype key; the office facts (kind, rank, colour, name) come from here. */
    private const ARCHETYPE = 'sancta-maria-sabbato';

    /** The votive office's stable identity — Marian, so carried in the sanctoral namespace. */
    private const SLUG = 'roman:sanctorale:sancta-maria-sabbato';

    private TemporalAttributes $attributes;

    /** @var array<string, TemporalObservance> Keyed by 'Y-m-d', in chronological order. */
    private array $offices;

    private function __construct(int $year, ?Corpus $corpus, ?string $editionDir)
    {
        if ($year < Computus::GREGORIAN_REFORM_YEAR) {
            throw new InvalidArgumentException(sprintf(
                'The Saturday Office of Our Lady is defined from %d onward; got %d.',
                Computus::GREGORIAN_REFORM_YEAR,
                $year
            ));
        }

        $this->attributes = new TemporalAttributes($corpus, $editionDir);
        $this->offices = $this->attributes->has(self::ARCHETYPE)
            ? $this->build($year)
            : [];
    }

    public static function forYear(int $year, ?Corpus $corpus = null, ?string $editionDir = null): self
    {
        return new self($year, $corpus, $editionDir);
    }

    /** The Saturday Office of Our Lady on a date, or null if none falls (or is said) there. */
    public function on(DateTimeImmutable $date): ?TemporalObservance
    {
        return $this->offices[$date->format('Y-m-d')] ?? null;
    }

    /**
     * @return array<string, TemporalObservance>
     */
    private function build(int $year): array
    {
        $office = $this->attributes->archetype(self::ARCHETYPE);

        $easter = PaschalSkeleton::forYear($year)->easter();
        $septuagesima = $easter->sub(new DateInterval('P63D')); // Easter - 63 (a Sunday)
        $ashWednesday = $easter->sub(new DateInterval('P46D')); // Easter - 46
        $holySaturday = $easter->sub(new DateInterval('P1D'));  // Easter - 1
        $pentecost = TemporalCalendar::addDays($easter, 49);    // Easter + 49
        $adventStart = ChristmasCycle::firstSundayOfAdvent($year);

        $offices = [];
        for (
            $date = $this->firstSaturday($year);
            (int) $date->format('Y') === $year;
            $date = TemporalCalendar::addDays($date, 7)
        ) {
            $season = $this->eligibleSeason(
                $date,
                $adventStart,
                $septuagesima,
                $ashWednesday,
                $holySaturday,
                $pentecost
            );
            if ($season === null) {
                continue; // an excluded season — the Office is not said
            }

            $offices[$date->format('Y-m-d')] = new TemporalObservance(
                ObservanceId::parse(self::SLUG),
                $office->kind(),
                $season,
                $office->rank(),
                $office->colour(),
                $office->renderName($date)
            );
        }

        return $offices;
    }

    /** The first Saturday on or after 1 January of the year. */
    private function firstSaturday(int $year): DateTimeImmutable
    {
        $jan1 = TemporalCalendar::utcDate($year, 1, 1);
        $offset = (6 - (int) $jan1->format('w') + 7) % 7; // w: 0 = Sunday … 6 = Saturday

        return TemporalCalendar::addDays($jan1, $offset);
    }

    /**
     * The season of an eligible Saturday, or null when the Office is NOT said there. The
     * Office is excluded in Advent (the First Sunday of Advent through 23 December), the
     * Christmas-vigil → Epiphany-octave block (24 December – 13 January), and Lent &
     * Passiontide (Ash Wednesday through Holy Saturday). Every other Saturday is eligible;
     * its season is read off the paschal skeleton so a resolved lady office reports the true
     * tempus (the day's own season still comes from the temporal filler, not this overlay).
     */
    private function eligibleSeason(
        DateTimeImmutable $date,
        DateTimeImmutable $adventStart,
        DateTimeImmutable $septuagesima,
        DateTimeImmutable $ashWednesday,
        DateTimeImmutable $holySaturday,
        DateTimeImmutable $pentecost
    ): ?Season {
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');

        // The Christmas-vigil (24 Dec) through the Octave of the Epiphany (13 Jan).
        if (($month === 12 && $day >= 24) || ($month === 1 && $day <= 13)) {
            return null;
        }
        // Advent: the First Sunday of Advent through 23 December (24 Dec+ is caught above).
        if ($date >= $adventStart) {
            return null;
        }
        // Lent & Passiontide: Ash Wednesday through Holy Saturday.
        if ($date >= $ashWednesday && $date <= $holySaturday) {
            return null;
        }

        // Eligible — the season by paschal position.
        if ($date >= $septuagesima && $date < $ashWednesday) {
            return Season::septuagesima();
        }
        if ($date > $holySaturday && $date <= $pentecost) {
            return Season::eastertide();
        }
        if ($date < $septuagesima) {
            return Season::epiphany(); // the time after the Epiphany (14 Jan – Septuagesima)
        }

        return Season::pentecost(); // the time after Pentecost (after Pentecost – Advent)
    }
}
