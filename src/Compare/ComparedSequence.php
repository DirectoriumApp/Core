<?php

declare(strict_types=1);

namespace Directorium\Core\Compare;

/**
 * A diff-tagged sequence for the comparison slider (#313): an ordered run of
 * {@see SequencePoint}s along one axis — a span of years (a fixed civil date scrubbed
 * across years, watching feasts move and seasons shift) or a run of editions (one date
 * across editions, watching the reforms take effect).
 *
 * Each point past the first carries the fields that changed from its predecessor, so a
 * UI can mark exactly where on the slider something changed.
 */
final class ComparedSequence
{
    /** Axis: the sequence scrubs across years. */
    public const AXIS_YEAR = 'year';

    /** Axis: the sequence scrubs across editions. */
    public const AXIS_EDITION = 'edition';

    private string $axis;

    /** @var list<SequencePoint> */
    private array $points;

    /**
     * @param list<SequencePoint> $points
     */
    public function __construct(string $axis, array $points)
    {
        $this->axis = $axis;
        $this->points = $points;
    }

    /** The axis this sequence scrubs along ({@see AXIS_YEAR} or {@see AXIS_EDITION}). */
    public function axis(): string
    {
        return $this->axis;
    }

    /** @return list<SequencePoint> */
    public function points(): array
    {
        return $this->points;
    }

    public function length(): int
    {
        return count($this->points);
    }

    /** @return list<SequencePoint> the points that differ from their predecessor */
    public function changedPoints(): array
    {
        return array_values(array_filter(
            $this->points,
            static fn (SequencePoint $point): bool => $point->changed()
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'axis' => $this->axis,
            'length' => $this->length(),
            'points' => array_map(
                static fn (SequencePoint $point): array => $point->toArray(),
                $this->points
            ),
        ];
    }
}
