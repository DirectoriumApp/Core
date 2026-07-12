<?php

declare(strict_types=1);

namespace Directorium\Core\Decree;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Attribute\RankClass;
use Directorium\Core\Corpus\Corpus;
use Directorium\Core\Corpus\CorpusRecord;
use Directorium\Core\Observance\Observance;
use Directorium\Core\Observance\ObservanceId;
use Directorium\Core\Observance\ObservanceKind;
use Directorium\Core\Overlay\OverlayOperationFactory;
use Directorium\Core\Sanctoral\SanctoralData;
use RuntimeException;

/**
 * The dated decrees of one edition, read from the corpus — the mechanism that turns a
 * frozen *editio typica* snapshot into a living calendar (docs/design/edition-governance.md).
 *
 * A snapshot such as `roman:novus-ordo-2002` is as static as any traditional edition;
 * its living-ness is expressed by this ordered stack of immutable, dated {@see Decree}s,
 * not by mutating the snapshot. The set is loaded once per edition ({@see forEdition()})
 * and applied per resolution year by the resolver, which asks it two things:
 *
 *  - {@see applyTo()} — the base sanctoral with every in-force fixed-date change folded
 *    in ({@see DecreedSanctoralData}); and
 *  - {@see officesFor()} — the movable memorials the in-force decrees add that year
 *    ({@see DecreeOffices}).
 *
 * Both are gated by each decree's effective date, so resolving a year replays exactly
 * the decrees in force by then and the result is reproducible. An edition with no decree
 * file loads an empty set, and both methods are then inert — the traditional editions
 * and a Novus-Ordo year before its first decree resolve exactly as without this class,
 * so the 1962 golden fixture is unmoved by construction.
 *
 * @internal Not part of the public API (docs/api-stability.md).
 */
final class DecreeSet
{
    /** @var list<Decree> In effective-date order, then id — the order changes are applied. */
    private array $decrees;

    /**
     * @param list<Decree> $decrees
     */
    public function __construct(array $decrees)
    {
        usort(
            $decrees,
            static fn (Decree $a, Decree $b): int
                => [$a->effective()->getTimestamp(), $a->id()] <=> [$b->effective()->getTimestamp(), $b->id()]
        );
        $this->decrees = $decrees;
    }

    /** The decree set an edition ships, or an empty set when the edition carries no decrees. */
    public static function forEdition(Corpus $corpus, string $editionDir): self
    {
        $decrees = [];
        foreach ($corpus->editionDecrees($editionDir) as $row) {
            $decrees[] = self::fromRow($row);
        }

        return new self($decrees);
    }

    public function isEmpty(): bool
    {
        return $this->decrees === [];
    }

    /** @return list<Decree> */
    public function all(): array
    {
        return $this->decrees;
    }

    /**
     * The base sanctoral with every in-force fixed-date decree operation applied for the
     * resolution year, or the base unchanged when no decree touches the sanctoral (so an
     * edition without such decrees pays nothing and resolves byte-identically).
     */
    public function applyTo(SanctoralData $base, int $year): SanctoralData
    {
        foreach ($this->decrees as $decree) {
            if ($decree->sanctoralOperations() !== []) {
                return new DecreedSanctoralData($base, $this->decrees, $year);
            }
        }

        return $base;
    }

    /**
     * The movable offices the in-force decrees add for the resolution year — each realized
     * onto its Easter-relative date and kept only when that date falls on or after the
     * decree's effective date (the movable analogue of the fixed-date gate in
     * {@see DecreedSanctoralData}).
     */
    public function officesFor(int $year): DecreeOffices
    {
        $byDate = [];
        foreach ($this->decrees as $decree) {
            foreach ($decree->movableAdditions() as $movable) {
                $date = $movable->dateFor($year);
                if ($date >= $decree->effective()) {
                    $byDate[$date->format('Y-m-d')] = $movable->realize();
                }
            }
        }

        return new DecreeOffices($byDate);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fromRow(array $row): Decree
    {
        $effective = new DateTimeImmutable(
            CorpusRecord::requireString($row, 'effective') . ' 00:00:00',
            new DateTimeZone('UTC')
        );

        $sanctoral = [];
        foreach (self::listOf($row, 'sanctoral') as $operationRow) {
            $sanctoral[] = OverlayOperationFactory::fromRow($operationRow);
        }

        $movable = [];
        foreach (self::listOf($row, 'movable') as $movableRow) {
            $movable[] = self::movable($movableRow);
        }

        return new Decree(
            CorpusRecord::requireString($row, 'id'),
            $effective,
            CorpusRecord::requireString($row, 'title'),
            $sanctoral,
            $movable
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function movable(array $row): MovableDecree
    {
        return new MovableDecree(
            new Observance(
                ObservanceId::parse(CorpusRecord::requireString($row, 'id')),
                ObservanceKind::fromString(CorpusRecord::requireString($row, 'kind')),
                CorpusRecord::titulars($row),
                CorpusRecord::names($row)
            ),
            RankClass::fromOrdinal(CorpusRecord::requireInt($row, 'rank')),
            CorpusRecord::elementColour($row),
            CorpusRecord::requireInt($row, 'easterOffset')
        );
    }

    /**
     * A nested list field of a decree row (`sanctoral` / `movable`), defaulting to empty.
     *
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private static function listOf(array $row, string $key): array
    {
        $value = $row[$key] ?? [];
        if (!is_array($value)) {
            throw new RuntimeException(sprintf('Corpus decree "%s" field is not a list.', $key));
        }

        $out = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException(sprintf('Corpus decree "%s" list has a non-object item.', $key));
            }
            /** @var array<string, mixed> $item */
            $out[] = $item;
        }

        return $out;
    }
}
