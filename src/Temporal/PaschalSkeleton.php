<?php

declare(strict_types=1);

namespace Introibo\Core\Temporal;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The paschal skeleton: every movable point of the temporal cycle that is
 * anchored to Easter, for one year.
 *
 * Each anchor is defined purely as a signed day-offset from Easter Sunday, read
 * from the corpus temporal skeleton via {@see TemporalDefinitions} (#42), so
 * nothing is hard-coded to a civil date — the actual dates are derived by counting
 * days from {@see Computus::gregorianEaster()}. Because the arithmetic is done in
 * whole days, February 29 in a leap year is handled for free: Ash Wednesday is
 * always exactly 46 days before Easter, leap year or not.
 *
 * These anchors are the load-bearing points the temporal-block fillers count
 * from (Septuagesima → Lent → Passiontide → Triduum → Eastertide → Pentecost
 * and its dependent feasts). The Major Rogation (25 April) is a fixed civil
 * date, not Easter-relative, so it is deliberately absent here.
 */
final class PaschalSkeleton
{
    /**
     * The offset table read from the corpus, memoised for the process.
     *
     * @var array<string, int>|null
     */
    private static ?array $offsets = null;

    private DateTimeImmutable $easter;

    private function __construct(DateTimeImmutable $easter)
    {
        $this->easter = $easter;
    }

    public static function forYear(int $year): self
    {
        return new self(Computus::gregorianEaster($year));
    }

    public static function fromEaster(DateTimeImmutable $easter): self
    {
        return new self($easter);
    }

    public function easter(): DateTimeImmutable
    {
        return $this->easter;
    }

    /**
     * The date of one anchor.
     *
     * @throws InvalidArgumentException if the anchor is not a known paschal anchor
     */
    public function date(string $anchor): DateTimeImmutable
    {
        $offsets = self::offsets();
        if (!isset($offsets[$anchor])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown paschal anchor "%s"; valid anchors: %s',
                $anchor,
                implode(', ', array_keys($offsets))
            ));
        }

        return $this->shift($offsets[$anchor]);
    }

    /**
     * Every anchor date for the year, keyed by slug, in chronological order.
     *
     * @return array<string, DateTimeImmutable>
     */
    public function all(): array
    {
        $dates = [];
        foreach (self::offsets() as $anchor => $offset) {
            $dates[$anchor] = $this->shift($offset);
        }

        return $dates;
    }

    /**
     * The offset table: anchor slug => days from Easter, read from the corpus
     * temporal skeleton (#42) and memoised for the process. Kept in chronological
     * order (ascending offset) so callers see the anchors as the year unfolds.
     *
     * @return array<string, int>
     */
    public static function offsets(): array
    {
        if (self::$offsets === null) {
            $map = TemporalDefinitions::default()->easterOffsets();
            asort($map);
            self::$offsets = $map;
        }

        return self::$offsets;
    }

    public function septuagesima(): DateTimeImmutable
    {
        return $this->date('septuagesima');
    }

    public function ashWednesday(): DateTimeImmutable
    {
        return $this->date('ash-wednesday');
    }

    public function ascension(): DateTimeImmutable
    {
        return $this->date('ascension');
    }

    public function pentecost(): DateTimeImmutable
    {
        return $this->date('pentecost');
    }

    public function corpusChristi(): DateTimeImmutable
    {
        return $this->date('corpus-christi');
    }

    private function shift(int $days): DateTimeImmutable
    {
        $interval = new DateInterval('P' . abs($days) . 'D');

        return $days >= 0 ? $this->easter->add($interval) : $this->easter->sub($interval);
    }
}
