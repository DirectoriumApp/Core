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
 * `{ord}` placeholder (filled with a Roman numeral) and/or a leading `{feria} `
 * marker (composed into a Feria-N / Sabbato name by {@see TemporalCalendar::feriaLatin()},
 * so the composition is byte-identical to the rest of the engine).
 */
final class TemporalArchetype
{
    private const FERIA_MARKER = '{feria} ';

    private const ORD_MARKER = '{ord}';

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
     * `{feria} `-marked (feria-composed) name and is otherwise unused.
     */
    public function renderName(DateTimeImmutable $date, int $ord = 0): string
    {
        $template = $this->nameTemplate;

        if (strncmp($template, self::FERIA_MARKER, strlen(self::FERIA_MARKER)) === 0) {
            $phrase = substr($template, strlen(self::FERIA_MARKER));

            return TemporalCalendar::feriaLatin($date, $this->fillOrdinal($phrase, $ord));
        }

        return $this->fillOrdinal($template, $ord);
    }

    private function fillOrdinal(string $text, int $ord): string
    {
        if (strpos($text, self::ORD_MARKER) === false) {
            return $text;
        }

        return str_replace(self::ORD_MARKER, TemporalCalendar::roman($ord), $text);
    }
}
