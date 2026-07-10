<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

/**
 * One edition's resolved view of a single date, reduced to the fields a calendar-mode
 * comparison diffs on ({@see ComparisonField}).
 *
 * A cell is built from the published day contract (the array
 * {@see \Directorium\Core\contract()} returns), so it compares exactly what a consumer
 * of that edition would see — not an internal representation that might drift from the
 * frozen output.
 */
final class EditionDayCell
{
    private string $edition;

    private ?string $feastId;

    private ?string $feastName;

    private ?int $rankOrdinal;

    private ?string $colour;

    /** @var list<string> */
    private array $commemorations;

    private ?string $season;

    /**
     * @param list<string> $commemorations
     */
    private function __construct(
        string $edition,
        ?string $feastId,
        ?string $feastName,
        ?int $rankOrdinal,
        ?string $colour,
        array $commemorations,
        ?string $season
    ) {
        $this->edition = $edition;
        $this->feastId = $feastId;
        $this->feastName = $feastName;
        $this->rankOrdinal = $rankOrdinal;
        $this->colour = $colour;
        $this->commemorations = $commemorations;
        $this->season = $season;
    }

    /**
     * Build a cell from a day contract resolved under `$edition`. The principal
     * celebration is the first `celebration` office; commemorations are reduced to a
     * sorted list of ids so the set compares order-independently.
     *
     * @param array<string, mixed> $contract
     */
    public static function fromContract(string $edition, array $contract): self
    {
        /** @var list<array<string, mixed>> $celebrations */
        $celebrations = $contract['celebration'] ?? [];
        $principal = $celebrations[0] ?? null;

        /** @var list<array<string, mixed>> $commems */
        $commems = $contract['commemoration'] ?? [];
        $commemorations = [];
        foreach ($commems as $commem) {
            $commemorations[] = (string) $commem['id'];
        }
        sort($commemorations, SORT_STRING);

        $season = $contract['season'] ?? null;

        return new self(
            $edition,
            $principal !== null ? (string) $principal['id'] : null,
            self::latinName($principal),
            $principal !== null ? (int) $principal['rankOrdinal'] : null,
            self::colourOf($principal),
            $commemorations,
            $season !== null ? (string) $season : null
        );
    }

    /**
     * @param array<string, mixed>|null $office
     */
    private static function latinName(?array $office): ?string
    {
        if ($office === null) {
            return null;
        }

        /** @var array<string, string> $names */
        $names = $office['names'] ?? [];

        return $names['la'] ?? null;
    }

    /**
     * @param array<string, mixed>|null $office
     */
    private static function colourOf(?array $office): ?string
    {
        if ($office === null) {
            return null;
        }

        /** @var array<string, mixed> $colour */
        $colour = $office['colour'] ?? [];

        return isset($colour['base']) ? (string) $colour['base'] : null;
    }

    public function edition(): string
    {
        return $this->edition;
    }

    public function feastId(): ?string
    {
        return $this->feastId;
    }

    public function feastName(): ?string
    {
        return $this->feastName;
    }

    public function rankOrdinal(): ?int
    {
        return $this->rankOrdinal;
    }

    public function colour(): ?string
    {
        return $this->colour;
    }

    /** @return list<string> */
    public function commemorations(): array
    {
        return $this->commemorations;
    }

    public function season(): ?string
    {
        return $this->season;
    }

    /**
     * The cell's comparable value per field — what {@see ComparedDay} and
     * {@see SequenceComparator} diff. Each value is compared with strict equality
     * (the commemorations list is pre-sorted, so equality is order-independent).
     *
     * @return array<string, mixed>
     */
    public function comparableValues(): array
    {
        return [
            ComparisonField::FEAST => $this->feastId,
            ComparisonField::RANK => $this->rankOrdinal,
            ComparisonField::COLOUR => $this->colour,
            ComparisonField::COMMEMORATIONS => $this->commemorations,
            ComparisonField::SEASON => $this->season,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'edition' => $this->edition,
            'feast' => $this->feastId,
            'feastName' => $this->feastName,
            'rank' => $this->rankOrdinal,
            'colour' => $this->colour,
            'commemorations' => $this->commemorations,
            'season' => $this->season,
        ];
    }
}
