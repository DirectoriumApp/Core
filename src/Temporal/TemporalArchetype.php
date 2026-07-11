<?php

declare(strict_types=1);

namespace Directorium\Core\Temporal;

use DateTimeImmutable;
use Directorium\Core\Attribute\ElementColour;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Observance\ObservanceKind;

/**
 * The edition-varying office facts of one temporal archetype (#42): its intrinsic
 * {@see ObservanceKind}, its 1960 {@see RankClass} and {@see ElementColour}, and
 * the Latin name TEMPLATE — read from the corpus, joined from the identity and
 * per-edition attribute shapes by the archetype key.
 *
 * The temporal fillers no longer carry these as literals; they compute which
 * archetype a date is (and its runtime ordinal, e.g. the week number) and read the
 * office from here. Only the naming GRAMMAR stays in code: the template may hold an
 * `{ord}` placeholder (filled with a Roman numeral), a `{dies}` placeholder (filled
 * with the civil day-of-month), and/or a leading `{feria} ` marker (composed into a
 * Feria-N / Sabbato name by {@see TemporalCalendar::feriaLatin()}, so the composition
 * is byte-identical to the rest of the engine). The `{dies}` placeholder is the
 * Novus-Ordo addition: its privileged and octave weekdays are designated by date —
 * "Die 17 decembris", "Die 2 ianuarii" — not by a week ordinal, so the day number is
 * read straight from the date (the month name stays literal in the template, since
 * every date-named stretch of the reformed cycle sits in a single month).
 */
final class TemporalArchetype
{
    private const FERIA_MARKER = '{feria} ';

    private const ORD_MARKER = '{ord}';

    private const DIES_MARKER = '{dies}';

    private ObservanceKind $kind;

    private RankClass $rank;

    private ElementColour $colour;

    private string $nameTemplate;

    public function __construct(
        ObservanceKind $kind,
        RankClass $rank,
        ElementColour $colour,
        string $nameTemplate
    ) {
        $this->kind = $kind;
        $this->rank = $rank;
        $this->colour = $colour;
        $this->nameTemplate = $nameTemplate;
    }

    public function kind(): ObservanceKind
    {
        return $this->kind;
    }

    public function rank(): RankClass
    {
        return $this->rank;
    }

    public function colour(): ElementColour
    {
        return $this->colour;
    }

    /**
     * The archetype's Latin name for a concrete day. `$ord` is the ordinal the
     * template needs (the week / Sunday / octave-day number); it is ignored by
     * templates that hold no `{ord}`. `$date` supplies the weekday for a
     * `{feria} `-marked (feria-composed) name and the day-of-month for a `{dies}`
     * placeholder, and is otherwise unused.
     */
    public function renderName(DateTimeImmutable $date, int $ord = 0): string
    {
        $template = $this->nameTemplate;

        if (strncmp($template, self::FERIA_MARKER, strlen(self::FERIA_MARKER)) === 0) {
            $phrase = substr($template, strlen(self::FERIA_MARKER));

            return TemporalCalendar::feriaLatin($date, $this->fill($phrase, $date, $ord));
        }

        return $this->fill($template, $date, $ord);
    }

    /**
     * Fill the day-varying placeholders of a template: `{ord}` with the Roman numeral
     * of the ordinal and `{dies}` with the civil day-of-month (no leading zero, e.g.
     * "Die 2 ianuarii"). A template that holds neither is returned unchanged.
     */
    private function fill(string $text, DateTimeImmutable $date, int $ord): string
    {
        $text = $this->fillOrdinal($text, $ord);

        if (strpos($text, self::DIES_MARKER) !== false) {
            $text = str_replace(self::DIES_MARKER, (string) (int) $date->format('j'), $text);
        }

        return $text;
    }

    private function fillOrdinal(string $text, int $ord): string
    {
        if (strpos($text, self::ORD_MARKER) === false) {
            return $text;
        }

        return str_replace(self::ORD_MARKER, TemporalCalendar::roman($ord), $text);
    }
}
