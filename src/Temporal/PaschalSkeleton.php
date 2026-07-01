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
 * Each anchor is defined purely as a signed day-offset from Easter Sunday (the
 * {@see OFFSETS} table), so nothing is hard-coded to a civil date — the actual
 * dates are derived by counting days from {@see Computus::gregorianEaster()}.
 * Because the arithmetic is done in whole days, February 29 in a leap year is
 * handled for free: Ash Wednesday is always exactly 46 days before Easter, leap
 * year or not.
 *
 * These anchors are the load-bearing points the temporal-block fillers count
 * from (Septuagesima → Lent → Passiontide → Triduum → Eastertide → Pentecost
 * and its dependent feasts). The Major Rogation (25 April) is a fixed civil
 * date, not Easter-relative, so it is deliberately absent here.
 */
final class PaschalSkeleton
{
    /**
     * Anchor slug => signed offset in days from Easter Sunday.
     *
     * @var array<string, int>
     */
    private const OFFSETS = [
        'septuagesima' => -63,
        'sexagesima' => -56,
        'quinquagesima' => -49,
        'ash-wednesday' => -46,
        'lent-1' => -42,
        'lent-ember-wednesday' => -39,
        'lent-ember-friday' => -37,
        'lent-ember-saturday' => -36,
        'lent-2' => -35,
        'lent-3' => -28,
        'lent-4' => -21,
        'passion-sunday' => -14,
        'palm-sunday' => -7,
        'maundy-thursday' => -3,
        'good-friday' => -2,
        'holy-saturday' => -1,
        'easter' => 0,
        'low-sunday' => 7,
        'rogation-monday' => 36,
        'rogation-tuesday' => 37,
        'rogation-wednesday' => 38,
        'ascension' => 39,
        'sunday-after-ascension' => 42,
        'pentecost' => 49,
        'whit-ember-wednesday' => 52,
        'whit-ember-friday' => 54,
        'whit-ember-saturday' => 55,
        'trinity-sunday' => 56,
        'corpus-christi' => 60,
        'sacred-heart' => 68,
    ];

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
        if (!isset(self::OFFSETS[$anchor])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown paschal anchor "%s"; valid anchors: %s',
                $anchor,
                implode(', ', array_keys(self::OFFSETS))
            ));
        }

        return $this->shift(self::OFFSETS[$anchor]);
    }

    /**
     * Every anchor date for the year, keyed by slug, in chronological order.
     *
     * @return array<string, DateTimeImmutable>
     */
    public function all(): array
    {
        $dates = [];
        foreach (self::OFFSETS as $anchor => $offset) {
            $dates[$anchor] = $this->shift($offset);
        }

        return $dates;
    }

    /**
     * The offset table: anchor slug => days from Easter.
     *
     * @return array<string, int>
     */
    public static function offsets(): array
    {
        return self::OFFSETS;
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
